<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Label nilai di atas batang chart.
 *
 * Batasnya bukan selera: kolom tersempit (chart harian, 31 batang) tinggal
 * sekitar 28px, jadi label yang lebih dari 6 karakter akan bertabrakan dengan
 * tetangganya.
 */
class KwhShortTest extends TestCase
{
    /** @return array<string, array{0: float, 1: string}> */
    public static function angka(): array
    {
        return [
            'nol' => [0, '0'],
            'satuan' => [7, '7'],
            'ratusan dibulatkan' => [410.4, '410'],
            'tepat di bawah seribu' => [999.4, '999'],
            // Ditulis penuh akan jadi "1.000" — rancu dengan pemisah ribuan.
            'membulat ke seribu' => [999.6, '1,0rb'],
            'ribuan satu desimal' => [9_840, '9,8rb'],
            'puluhan ribu' => [45_230, '45,2rb'],
            'tepat di bawah seratus ribu' => [99_940, '99,9rb'],
            // Tanpa penjagaan, ini lolos sebagai "100,0rb" (7 karakter).
            'membulat ke seratus ribu' => [99_950, '100rb'],
            'ratusan ribu tanpa desimal' => [300_500, '301rb'],
            'jutaan' => [1_250_000, '1,3jt'],
            'negatif tetap terbaca' => [-50, '-50'],
        ];
    }

    /**
     * @dataProvider angka
     */
    public function test_angka_diringkas_sesuai_besarannya(float $value, string $expected): void
    {
        $this->assertSame($expected, kwh_short($value));
    }

    /**
     * @dataProvider angka
     */
    public function test_label_tidak_pernah_lebih_dari_enam_karakter(float $value): void
    {
        $this->assertLessThanOrEqual(6, mb_strlen(kwh_short($value)));
    }

    public function test_nilai_null_dianggap_nol(): void
    {
        $this->assertSame('0', kwh_short(null));
    }
}
