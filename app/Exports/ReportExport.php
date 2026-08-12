<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel generik untuk kedua laporan: baris sudah disiapkan
 * ReportService, kelas ini hanya mengurus judul kolom dan gaya.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(
        private readonly array $headings,
        private readonly Collection $rows,
    ) {
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows->map(fn ($row) => array_values((array) $row))->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
