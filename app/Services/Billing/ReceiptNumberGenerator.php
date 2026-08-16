<?php

namespace App\Services\Billing;

use App\Models\InvoicePayment;
use Illuminate\Support\Carbon;

/**
 * Menyusun nomor kuitansi dari pola di setting sistem.
 *
 * Mengikuti pola yang sama dengan InvoiceNumberGenerator:
 *   {YYYY} tahun 4 digit   {YY} tahun 2 digit
 *   {MM}   bulan 2 digit   {SEQ} nomor urut dalam bulan itu
 *
 * Contoh: KW/{YYYY}/{MM}/{SEQ} → KW/2026/08/001
 *
 * Urutan dihitung per bulan penerbitan, bukan per periode tagihan seperti
 * invoice: kuitansi mengikuti kapan uang diterima, dan pembayaran untuk
 * periode lama bisa saja masuk bulan ini.
 */
class ReceiptNumberGenerator
{
    public function generate(Carbon $issuedAt, int $sequence): string
    {
        $format = (string) setting('receipt_number_format', 'KW/{YYYY}/{MM}/{SEQ}');
        $padding = max(1, (int) setting('invoice_number_padding', 3));

        return strtr($format, [
            '{YYYY}' => $issuedAt->format('Y'),
            '{YY}' => $issuedAt->format('y'),
            '{MM}' => $issuedAt->format('m'),
            '{SEQ}' => str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Nomor berikutnya untuk bulan penerbitan tertentu.
     *
     * Dinaikkan sampai menemukan nomor yang belum terpakai, sama seperti
     * penomoran invoice — melindungi dari tabrakan bila dua kuitansi terbit
     * pada saat yang hampir bersamaan.
     */
    public function next(Carbon $issuedAt): string
    {
        $sequence = InvoicePayment::whereNotNull('receipt_no')
            ->whereYear('receipt_issued_at', $issuedAt->year)
            ->whereMonth('receipt_issued_at', $issuedAt->month)
            ->count() + 1;

        do {
            $number = $this->generate($issuedAt, $sequence);
            $sequence++;
        } while (InvoicePayment::where('receipt_no', $number)->exists());

        return $number;
    }
}
