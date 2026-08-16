<?php

namespace App\Services\Billing;

use App\Mail\ReceiptMail;
use App\Mail\ReceiptVoidedMail;
use App\Models\InvoicePayment;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Kuitansi: tanda terima uang yang diterbitkan aplikasi untuk pelanggan.
 *
 * Terbit per PEMBAYARAN, bukan per invoice. Invoice yang dicicil tiga kali
 * menghasilkan tiga kuitansi, masing-masing untuk jumlah yang benar-benar
 * diterima saat itu — inilah perlakuan yang benar untuk tanda terima uang,
 * dan sekaligus menjawab kebutuhan pembayaran sebagian tanpa aturan khusus.
 */
class ReceiptService
{
    public function __construct(private readonly ReceiptNumberGenerator $numbers)
    {
    }

    /**
     * Memberi nomor kuitansi bila belum punya, lalu mengembalikannya.
     *
     * Angka pada kuitansi di-snapshot di sini dan tidak dihitung ulang saat
     * PDF dibuka: pembayaran bertanggal mundur yang diinput belakangan akan
     * menggeser hasil hitungan, sementara dokumen yang sudah dikirim ke
     * pelanggan harus tetap berbunyi sama selamanya.
     */
    public function issue(InvoicePayment $payment): InvoicePayment
    {
        if ($payment->hasReceipt()) {
            return $payment;
        }

        return DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $paidTotal = (float) $invoice->payments()->sum('amount');
            $issuedAt = now();

            $payment->forceFill([
                'receipt_no' => $this->numbers->next($issuedAt),
                'receipt_issued_at' => $issuedAt,
                'receipt_paid_total' => $paidTotal,
                'receipt_outstanding_after' => max(0, (float) $invoice->total_amount - $paidTotal),
            ])->save();

            return $payment;
        });
    }

    public function pdf(InvoicePayment $payment)
    {
        $payment = $this->issue($payment);

        return Pdf::loadView('billing.receipts.pdf', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
        ])->setPaper('a5', 'landscape');
    }

    public function filename(InvoicePayment $payment): string
    {
        return Str::slug($payment->receipt_no ?? 'kuitansi').'.pdf';
    }

    /**
     * Mengirim kuitansi ke email pelanggan beserta lampiran PDF.
     *
     * $queue dipakai pengiriman terjadwal, agar satu SMTP yang lambat tidak
     * menahan seluruh antrean kuitansi yang jatuh tempo hari itu.
     */
    public function email(InvoicePayment $payment, bool $queue = false): void
    {
        // Sengaja dibaca ulang dari database, bukan memakai relasi yang sudah
        // termuat: pemanggil bisa saja memegang objek yang dimuat sebelum
        // invoicenya dibatalkan, dan penjaga di bawah ini justru ada untuk
        // mencegah kuitansi invoice batal terlanjur terkirim.
        $invoice = $payment->invoice()->with('customer')->first();

        if (!$invoice) {
            throw new \RuntimeException('Pembayaran ini tidak terhubung ke invoice mana pun.');
        }

        if ($invoice->isCancelled()) {
            throw new \RuntimeException('Invoice sudah dibatalkan, kuitansinya tidak bisa dikirim.');
        }

        // Supaya isi email memakai data yang sama dengan yang baru diperiksa.
        $payment->setRelation('invoice', $invoice);

        $email = $invoice->customer?->email;

        if (!$email) {
            throw new \RuntimeException('Pelanggan belum punya alamat email.');
        }

        $payment = $this->issue($payment);

        $mailer = Mail::to($email);
        $mailable = new ReceiptMail($payment, $this);

        $queue ? $mailer->queue($mailable) : $mailer->send($mailable);

        $payment->forceFill(['receipt_sent_at' => now()])->save();

        ActivityLogger::log(
            'send_receipt',
            $payment,
            "Kirim kuitansi {$payment->receipt_no} ke {$email}",
        );
    }

    /**
     * Memberi tahu pelanggan bahwa kuitansi yang pernah diterimanya dibatalkan.
     *
     * Dipanggil saat pembatalan paksa: dokumennya sudah ada di tangan
     * pelanggan, jadi menariknya diam-diam berarti membiarkan mereka memegang
     * bukti yang tidak lagi berlaku tanpa tahu.
     */
    public function notifyVoided(InvoicePayment $payment, ?string $reason = null): void
    {
        $email = $payment->invoice?->customer?->email;

        if (!$email) {
            return;
        }

        Mail::to($email)->queue(new ReceiptVoidedMail(
            receiptNo: $payment->receipt_no,
            invoiceNo: $payment->invoice?->invoice_no,
            customerName: $payment->invoice?->customer_name,
            amount: (float) $payment->amount,
            reason: $reason,
        ));
    }
}
