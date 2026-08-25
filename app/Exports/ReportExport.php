<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel generik untuk seluruh laporan: baris sudah disiapkan
 * ReportService, kelas ini hanya mengurus judul kolom, gaya, dan format angka.
 */
class ReportExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles
{
    /** Angka teknis: kWh, stand meter, kW, kVA, tegangan, arus. */
    private const FORMAT_TECHNICAL = '#,##0.00';

    /** Nominal rupiah, dibulatkan ke rupiah penuh seperti di layar. */
    private const FORMAT_CURRENCY = '#,##0';

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

    /**
     * Format angka per kolom.
     *
     * Nilainya tetap dikirim sebagai angka, bukan teks: sel yang berisi
     * "1.234,56" sebagai string tidak bisa di-SUM maupun disortir, dan
     * laporan Excel dipakai justru untuk itu. Yang diatur di sini hanya
     * tampilannya.
     *
     * Kolom ditentukan dari judulnya — bertanda (Rp) berarti nominal — lalu
     * disaring lagi oleh isi barisnya, sehingga kolom teks seperti "Status"
     * atau "Sumber" tidak ikut diberi format angka.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->headings as $index => $heading) {
            if (!$this->columnIsNumeric($index)) {
                continue;
            }

            $column = Coordinate::stringFromColumnIndex($index + 1);
            $formats[$column] = str_contains((string) $heading, '(Rp)')
                ? self::FORMAT_CURRENCY
                : self::FORMAT_TECHNICAL;
        }

        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Sebuah kolom dianggap angka bila ada satu saja nilai numerik di
     * dalamnya. Nilai null dilewati, bukan dianggap bukan-angka: kolom
     * seperti "Beban Puncak (kW)" kosong pada meter yang belum punya
     * pembacaan, dan baris pertama kebetulan kosong tidak boleh membuat
     * seluruh kolomnya kehilangan format.
     */
    private function columnIsNumeric(int $index): bool
    {
        foreach ($this->rows as $row) {
            $value = array_values((array) $row)[$index] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            return is_int($value) || is_float($value);
        }

        return false;
    }
}
