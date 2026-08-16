<?php

namespace App\Exports;

use App\Services\Billing\PaymentImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Berkas contoh untuk impor pembayaran.
 *
 * Berisi dua baris contoh yang sengaja tidak dikosongkan: kolom tanggal dan
 * nominal punya format yang mudah salah (Excel gemar mengubah tanggal menjadi
 * format lokal dan nominal menjadi teks berpemisah ribuan), dan contoh nyata
 * lebih jelas daripada penjelasan di dokumen terpisah.
 */
class PaymentTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return PaymentImportService::COLUMNS;
    }

    public function array(): array
    {
        return [
            ['2026-08-15', 'INV/2026/07/001', 'PT Sinar Abadi Logistik', 4500000, 'transfer', 'TRF00123', ''],
            ['2026-08-15', 'INV/2026/07/002', 'CV Mitra Cold Storage', 3200000, 'transfer', 'TRF00124', 'pembayaran sebagian'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Nominal ditulis sebagai angka murni tanpa pemisah ribuan; kalau
        // dibiarkan mengikuti format bawaan, hasil pivot dari mutasi bank
        // sering terbaca sebagai teks dan gagal divalidasi.
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
