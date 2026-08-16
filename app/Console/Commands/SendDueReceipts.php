<?php

namespace App\Console\Commands;

use App\Models\InvoicePayment;
use App\Services\Billing\ReceiptService;
use Illuminate\Console\Command;

/**
 * Mengirim kuitansi yang sudah lewat masa tunggu.
 *
 * Masa tunggu (receipt_auto_send_days) ada supaya salah input pembayaran
 * sempat ketahuan dan ditarik sebelum kuitansinya terlanjur sampai ke
 * pelanggan. Menarik kembali dokumen yang sudah dikirim jauh lebih mahal
 * daripada menunggu beberapa hari.
 */
class SendDueReceipts extends Command
{
    protected $signature = 'receipts:send-due
                            {--dry-run : Tampilkan yang akan dikirim tanpa benar-benar mengirim}';

    protected $description = 'Kirim kuitansi pembayaran yang sudah lewat masa tunggu';

    public function handle(ReceiptService $receipts): int
    {
        if (!setting('receipt_auto_send', false)) {
            $this->info('Kirim kuitansi otomatis sedang dimatikan di Setting.');

            return self::SUCCESS;
        }

        $days = max(0, (int) setting('receipt_auto_send_days', 3));
        $cutoff = now()->subDays($days);

        $due = InvoicePayment::query()
            ->whereNull('receipt_sent_at')
            ->where('created_at', '<=', $cutoff)
            // Invoice yang batal tidak boleh dikirimi kuitansi, dan pelanggan
            // tanpa email jelas tidak bisa menerimanya.
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'cancelled')
                ->whereHas('customer', fn ($sub) => $sub->whereNotNull('email')))
            ->with('invoice.customer')
            ->get();

        if ($due->isEmpty()) {
            $this->info('Tidak ada kuitansi yang jatuh tempo kirim.');

            return self::SUCCESS;
        }

        $this->info("{$due->count()} kuitansi siap dikirim (masa tunggu {$days} hari).");

        if ($this->option('dry-run')) {
            foreach ($due as $payment) {
                $this->line("  {$payment->invoice->invoice_no} — ".rupiah($payment->amount));
            }

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        foreach ($due as $payment) {
            try {
                // Di-queue supaya satu SMTP yang lambat tidak menahan sisanya.
                $receipts->email($payment, queue: true);
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
                $this->warn("  Gagal {$payment->invoice?->invoice_no}: {$e->getMessage()}");
            }
        }

        $this->info("Selesai — {$sent} terkirim, {$failed} gagal.");

        return self::SUCCESS;
    }
}
