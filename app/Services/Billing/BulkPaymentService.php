<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentBatch;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan pembayaran untuk banyak invoice sekaligus, beserta pembatalannya.
 *
 * Dipakai dua jalur: "tandai lunas" dari daftar invoice, dan impor berkas.
 * Keduanya menghasilkan satu PaymentBatch sehingga bisa ditarik kembali
 * sebagai satu kesatuan bila ternyata salah.
 */
class BulkPaymentService
{
    /** Batas invoice per operasi, menjaga satu request tidak berjalan terlalu lama. */
    public const MAX_PER_BATCH = 500;

    /**
     * Melunasi sisa tagihan sejumlah invoice sekaligus.
     *
     * Mengikuti pola InvoiceGenerator: satu invoice yang bermasalah dicatat
     * sebagai kegagalan, bukan membatalkan seluruh batch — kalau tidak, satu
     * baris rusak membuat 99 pembayaran yang sah ikut hilang.
     *
     * @param  array<int>  $invoiceIds
     * @return array{batch: ?PaymentBatch, created: int, total: float, skipped: array<int, string>}
     */
    public function markPaid(array $invoiceIds, string $paymentDate, string $method, ?string $notes = null): array
    {
        $invoiceIds = array_slice(array_unique($invoiceIds), 0, self::MAX_PER_BATCH);

        $invoices = Invoice::whereIn('id', $invoiceIds)->get();
        $skipped = [];
        $eligible = [];

        foreach ($invoices as $invoice) {
            $reason = $this->rejectionReason($invoice);

            if ($reason) {
                $skipped[] = "{$invoice->invoice_no}: {$reason}";

                continue;
            }

            $eligible[] = $invoice;
        }

        if (empty($eligible)) {
            return ['batch' => null, 'created' => 0, 'total' => 0.0, 'skipped' => $skipped];
        }

        $batch = DB::transaction(function () use ($eligible, $paymentDate, $method, $notes) {
            $batch = PaymentBatch::create([
                'type' => 'bulk',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $total = 0.0;

            foreach ($eligible as $invoice) {
                $amount = $invoice->outstanding;

                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'payment_date' => $paymentDate,
                    'amount' => $amount,
                    'method' => $method,
                    'notes' => $notes,
                    'recorded_by' => auth()->id(),
                    'payment_batch_id' => $batch->id,
                ]);

                $total += $amount;
            }

            $batch->update(['payment_count' => count($eligible), 'total_amount' => $total]);

            return $batch;
        });

        ActivityLogger::log(
            'bulk_payment',
            $batch,
            "Tandai lunas {$batch->payment_count} invoice sebesar ".rupiah($batch->total_amount),
        );

        return [
            'batch' => $batch,
            'created' => $batch->payment_count,
            'total' => (float) $batch->total_amount,
            'skipped' => $skipped,
        ];
    }

    /**
     * Jumlah pembayaran dalam batch yang kuitansinya sudah dikirim ke pelanggan.
     */
    public function sentReceiptCount(PaymentBatch $batch): int
    {
        return $batch->payments()->whereNotNull('receipt_sent_at')->count();
    }

    /**
     * Menarik kembali seluruh pembayaran dalam satu batch.
     *
     * Pembayaran dihapus satu per satu, bukan lewat delete() massal: status
     * invoice disegarkan oleh model event InvoicePayment, dan event itu tidak
     * jalan pada penghapusan tingkat query. Tanpa ini, invoice akan tetap
     * berstatus lunas padahal pembayarannya sudah tidak ada.
     *
     * Batch yang kuitansinya sudah terkirim ditolak: dokumennya sudah ada di
     * tangan pelanggan. Pembatalannya hanya lewat $force — dan pemanggil wajib
     * memastikan penggunanya berhak (payment.force_revert) — yang otomatis
     * mengirim pemberitahuan pembatalan ke tiap pelanggan yang terdampak.
     *
     * @return int Jumlah pembayaran yang ditarik.
     *
     * @throws \RuntimeException bila kuitansinya sudah terkirim dan tidak dipaksa.
     */
    public function revert(PaymentBatch $batch, bool $force = false, ?string $reason = null): int
    {
        if ($batch->isReverted()) {
            return 0;
        }

        $sent = $this->sentReceiptCount($batch);

        if ($sent > 0 && !$force) {
            throw new \RuntimeException(
                "Kuitansi untuk {$sent} pembayaran di batch ini sudah dikirim ke pelanggan, "
                .'jadi batch-nya tidak bisa dibatalkan begitu saja.',
            );
        }

        $receipts = app(ReceiptService::class);
        $notify = [];

        $count = DB::transaction(function () use ($batch, &$notify) {
            $count = 0;

            foreach ($batch->payments()->with('invoice.customer')->get() as $payment) {
                if ($payment->receiptSent()) {
                    $notify[] = $payment;
                }

                $payment->delete();
                $count++;
            }

            $batch->forceFill([
                'reverted_at' => now(),
                'reverted_by' => auth()->id(),
            ])->save();

            return $count;
        });

        // Dikirim setelah transaksi selesai: kalau penghapusannya gagal di
        // tengah jalan, pelanggan tidak boleh terlanjur menerima kabar bahwa
        // kuitansinya batal.
        foreach ($notify as $payment) {
            $receipts->notifyVoided($payment, $reason);
        }

        ActivityLogger::log(
            'revert_payment_batch',
            $batch,
            "Batalkan batch pembayaran #{$batch->id} — {$count} pembayaran ditarik kembali"
                .($notify ? ', '.count($notify).' pelanggan diberi tahu kuitansinya batal' : ''),
        );

        return $count;
    }

    /**
     * Alasan sebuah invoice tidak boleh menerima pembayaran massal, atau null
     * bila boleh.
     *
     * Aturannya sengaja sama dengan pencatatan satuan di PaymentPage: draft
     * dan batal ditolak. Lunas ikut ditolak di sini karena tidak ada lagi yang
     * perlu dibayar — memasukkannya hanya menghasilkan pembayaran nol rupiah.
     */
    public function rejectionReason(Invoice $invoice): ?string
    {
        return match (true) {
            $invoice->status === 'draft' => 'masih draft, terbitkan dulu',
            $invoice->status === 'cancelled' => 'sudah dibatalkan',
            $invoice->outstanding <= 0 => 'sudah lunas',
            default => null,
        };
    }
}
