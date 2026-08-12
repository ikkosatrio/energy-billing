<?php

namespace Tests\Unit;

use App\Services\Tariff\ScheduleValidator;
use PHPUnit\Framework\TestCase;

class ScheduleValidatorTest extends TestCase
{
    private ScheduleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ScheduleValidator();
    }

    public function test_jadwal_yang_benar_lolos_validasi(): void
    {
        $errors = $this->validator->validate([
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '17:00', 'tariff_type' => 'WBP'],
            ['start_time' => '22:00', 'tariff_type' => 'LWBP'],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_jam_mulai_bukan_kelipatan_15_menit_ditolak(): void
    {
        $errors = $this->validator->validate([
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '17:07', 'tariff_type' => 'WBP'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('kelipatan 15 menit', implode(' ', $errors));
    }

    public function test_jam_mulai_duplikat_ditolak(): void
    {
        $errors = $this->validator->validate([
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '17:00', 'tariff_type' => 'WBP'],
            ['start_time' => '17:00', 'tariff_type' => 'LWBP'],
        ]);

        $this->assertStringContainsString('duplikat', implode(' ', $errors));
    }

    public function test_periode_lebih_dari_batas_ditolak(): void
    {
        $periods = [];
        for ($i = 0; $i < 13; $i++) {
            $periods[] = ['start_time' => sprintf('%02d:00', $i), 'tariff_type' => 'LWBP'];
        }

        $errors = $this->validator->validate($periods);

        $this->assertStringContainsString('tidak boleh lebih dari 12', implode(' ', $errors));
    }

    public function test_jadwal_yang_tidak_mulai_tengah_malam_ditolak(): void
    {
        $errors = $this->validator->validate([
            ['start_time' => '06:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '17:00', 'tariff_type' => 'WBP'],
        ]);

        $this->assertStringContainsString('00:00', implode(' ', $errors));
    }

    public function test_normalize_mengurutkan_dan_menyambung_periode(): void
    {
        $rows = $this->validator->normalize([
            ['start_time' => '17:00', 'tariff_type' => 'WBP'],
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '22:00', 'tariff_type' => 'LWBP'],
        ]);

        $this->assertSame('00:00', $rows[0]['start_time']);
        $this->assertSame('17:00', $rows[0]['end_time']);
        $this->assertSame('17:00', $rows[1]['start_time']);
        $this->assertSame('22:00', $rows[1]['end_time']);
        // Baris terakhir ditutup di 00:00 yang mewakili pukul 24:00.
        $this->assertSame('00:00', $rows[2]['end_time']);
        $this->assertSame([1, 2, 3], array_column($rows, 'sequence'));
    }

    public function test_total_durasi_genap_24_jam(): void
    {
        $rows = $this->validator->normalize([
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '17:00', 'tariff_type' => 'WBP'],
            ['start_time' => '22:00', 'tariff_type' => 'LWBP'],
        ]);

        $totals = $this->validator->totals($rows);

        $this->assertSame(5 * 60, $totals['WBP']);
        $this->assertSame(19 * 60, $totals['LWBP']);
        $this->assertSame(24 * 60, $totals['LWBP'] + $totals['WBP']);
    }
}
