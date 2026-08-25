<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Format angka teknis di seluruh aplikasi.
 *
 * Kolomnya disimpan decimal:2 — kWh, stand meter, kW, tegangan, arus — jadi
 * lapisan tampilan tidak boleh membulatkannya. Selisih di bawah satu satuan
 * tidak kelihatan per baris, tapi total di layar jadi tidak pernah cocok
 * dengan yang ditagihkan invoice.
 */
class KwhFormatTest extends TestCase
{
    /** @return array<string, array{0: float|int|string|null, 1: string}> */
    public static function angka(): array
    {
        return [
            'nol' => [0, '0,00'],
            'bilangan bulat tetap dapat desimal' => [382, '382,00'],
            'satu desimal dilengkapi' => [382.5, '382,50'],
            'dua desimal utuh' => [382.47, '382,47'],
            'pemisah ribuan indonesia' => [1_270_280.5, '1.270.280,50'],
            'pembulatan hanya di desimal ketiga' => [0.125, '0,13'],
            'nilai kecil tidak jatuh ke nol' => [0.4, '0,40'],
            'negatif' => [-50.25, '-50,25'],
            'null dianggap nol' => [null, '0,00'],
            'string numerik' => ['1234.5', '1.234,50'],
        ];
    }

    /**
     * @dataProvider angka
     */
    public function test_angka_diformat_dua_desimal(float|int|string|null $value, string $expected): void
    {
        $this->assertSame($expected, kwh($value));
    }

    /** Tidak ada nilai yang tampil sebagai bilangan bulat tanpa desimal. */
    public function test_tidak_pernah_menghasilkan_bilangan_bulat(): void
    {
        foreach ([0, 1, 7, 999, 1000, 45_230, 1_250_000] as $value) {
            $this->assertStringContainsString(',', kwh($value), "kwh({$value}) kehilangan desimal");
        }
    }
}
