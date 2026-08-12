<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * Menyusun nomor invoice dari pola di setting sistem.
 *
 * Placeholder yang dikenali:
 *   {YYYY} tahun 4 digit   {YY} tahun 2 digit
 *   {MM}   bulan 2 digit   {SEQ} nomor urut dalam periode
 *
 * Contoh: INV/{YYYY}/{MM}/{SEQ} → INV/2026/08/001
 */
class InvoiceNumberGenerator
{
    public function generate(Carbon $issueDate, int $sequence): string
    {
        $format = (string) setting('invoice_number_format', 'INV/{YYYY}/{MM}/{SEQ}');
        $padding = max(1, (int) setting('invoice_number_padding', 3));

        return strtr($format, [
            '{YYYY}' => $issueDate->format('Y'),
            '{YY}' => $issueDate->format('y'),
            '{MM}' => $issueDate->format('m'),
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Nomor urut berikutnya untuk sebuah periode.
     *
     * Dihitung dari jumlah invoice yang sudah ada di periode itu, lalu dinaikkan
     * sampai menemukan nomor yang belum terpakai — melindungi dari tabrakan
     * bila ada invoice yang pernah dibatalkan atau dinomori manual.
     */
    public function nextFor(int $billingPeriodId, Carbon $issueDate): string
    {
        $sequence = Invoice::where('billing_period_id', $billingPeriodId)->count() + 1;

        do {
            $number = $this->generate($issueDate, $sequence);
            $sequence++;
        } while (Invoice::where('invoice_no', $number)->exists());

        return $number;
    }
}
