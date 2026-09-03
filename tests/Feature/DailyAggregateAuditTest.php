<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Agregat harian yang bolong membuat chart bulanan membaca LEBIH RENDAH
 * daripada invoice, karena chart menjumlahkan `meter_reading_dailies`
 * sedangkan invoice menghitung ulang dari selisih stand meter.
 *
 * Jadwal rutin hanya menyentuh kemarin dan hari ini, jadi hari yang
 * agregatnya gagal dibuat tidak pernah terkejar sendiri. Dua hal diuji di
 * sini: audit yang menemukannya, dan pembangunan ulang serentang yang
 * memperbaikinya.
 */
class DailyAggregateAuditTest extends TestCase
{
    use RefreshDatabase;

    private PowerMeter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);

        Carbon::setTestNow('2026-08-20 09:00:00');

        $this->meter = PowerMeter::create([
            'code' => 'PM001', 'name' => 'Panel Uji', 'multiplier' => 1, 'status' => 'active',
        ]);

        $this->pembacaan();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Sepuluh hari, dua pembacaan sehari, naik 1 kWh LWBP tiap pembacaan.
     * Ada juga satu pembacaan penutup 31 Juli sebagai stand awal periode.
     */
    private function pembacaan(): void
    {
        $rows = [];
        $lwbp = 100.0;
        $wbp = 50.0;

        $rows[] = [
            'power_meter_id' => $this->meter->id, 'read_at' => '2026-07-31 23:30:00',
            'stand_lwbp' => $lwbp, 'stand_wbp' => $wbp, 'source' => 'api',
        ];

        for ($hari = 1; $hari <= 10; $hari++) {
            foreach (['06:00:00', '18:00:00'] as $jam) {
                $lwbp += 1.0;
                $wbp += 0.5;
                $rows[] = [
                    'power_meter_id' => $this->meter->id,
                    'read_at' => sprintf('2026-08-%02d %s', $hari, $jam),
                    'stand_lwbp' => $lwbp, 'stand_wbp' => $wbp, 'source' => 'api',
                ];
            }
        }

        MeterReading::insert($rows);
    }

    private function agregasiPenuh(): void
    {
        $this->artisan('readings:aggregate', ['--from' => '2026-08-01', '--to' => '2026-08-10'])
            ->assertSuccessful();
    }

    private function sumHarian(): float
    {
        return (float) MeterReadingDaily::where('power_meter_id', $this->meter->id)
            ->get()
            ->sum(fn (MeterReadingDaily $row) => $row->total_kwh);
    }

    // ── Agregasi rentang ────────────────────────────────────────────────

    public function test_agregasi_rentang_membangun_seluruh_hari(): void
    {
        $this->agregasiPenuh();

        $this->assertSame(10, MeterReadingDaily::where('power_meter_id', $this->meter->id)->count());

        // 20 pembacaan × 1 kWh LWBP + 20 × 0,5 kWh WBP = 30 kWh.
        $this->assertEqualsWithDelta(30.0, $this->sumHarian(), 0.01);
    }

    public function test_opsi_month_membangun_bulan_penuh_sampai_hari_ini(): void
    {
        $this->artisan('readings:aggregate', ['--month' => '2026-08'])->assertSuccessful();

        $this->assertSame(10, MeterReadingDaily::where('power_meter_id', $this->meter->id)->count());
    }

    public function test_rentang_terbalik_ditolak(): void
    {
        $this->artisan('readings:aggregate', ['--from' => '2026-08-10', '--to' => '2026-08-01'])
            ->assertFailed();
    }

    public function test_agregasi_ulang_tidak_menggandakan(): void
    {
        $this->agregasiPenuh();
        $sebelum = $this->sumHarian();

        $this->agregasiPenuh();

        $this->assertSame(10, MeterReadingDaily::where('power_meter_id', $this->meter->id)->count());
        $this->assertEqualsWithDelta($sebelum, $this->sumHarian(), 0.01);
    }

    // ── Audit ───────────────────────────────────────────────────────────

    public function test_audit_bersih_saat_agregat_lengkap(): void
    {
        $this->agregasiPenuh();

        $this->artisan('readings:audit', ['--meter' => 'PM001', '--month' => '2026-08'])
            ->expectsOutputToContain('cocok dengan perhitungan invoice')
            ->assertSuccessful();
    }

    public function test_audit_menemukan_hari_yang_agregatnya_hilang(): void
    {
        $this->agregasiPenuh();

        // Meniru hari yang terlewat karena scheduler mati.
        MeterReadingDaily::where('power_meter_id', $this->meter->id)
            ->whereDate('date', '2026-08-05')
            ->delete();

        $this->artisan('readings:audit', ['--meter' => 'PM001', '--month' => '2026-08'])
            ->expectsOutputToContain('2026-08-05')
            ->expectsOutputToContain('tidak cocok')
            ->assertSuccessful();
    }

    public function test_agregasi_rentang_memperbaiki_hari_yang_hilang(): void
    {
        $this->agregasiPenuh();
        $lengkap = $this->sumHarian();

        MeterReadingDaily::where('power_meter_id', $this->meter->id)
            ->whereDate('date', '2026-08-05')
            ->delete();

        $this->assertLessThan($lengkap, $this->sumHarian());

        $this->artisan('readings:aggregate', ['--month' => '2026-08'])->assertSuccessful();

        $this->assertEqualsWithDelta($lengkap, $this->sumHarian(), 0.01);

        $this->artisan('readings:audit', ['--meter' => 'PM001', '--month' => '2026-08'])
            ->expectsOutputToContain('cocok dengan perhitungan invoice')
            ->assertSuccessful();
    }

    // ── Pemilihan bulan ─────────────────────────────────────────────────

    public function test_audit_bisa_dibatasi_satu_bulan(): void
    {
        $this->agregasiPenuh();

        $this->artisan('readings:audit', ['--meter' => 'PM001', '--month' => '2026-08', '--all' => true])
            ->expectsOutputToContain('2026-08')
            ->assertSuccessful();
    }

    public function test_audit_bisa_dibatasi_rentang_bulan(): void
    {
        $this->agregasiPenuh();

        $this->artisan('readings:audit', [
            '--meter' => 'PM001', '--from' => '2026-07', '--to' => '2026-08', '--all' => true,
        ])->assertSuccessful();
    }

    /**
     * Format salah harus dijawab kalimat, bukan stack trace: perintah ini
     * diketik langsung di terminal produksi.
     */
    public function test_format_bulan_yang_salah_ditolak_dengan_pesan_jelas(): void
    {
        $this->artisan('readings:audit', ['--month' => 'agustus'])
            ->expectsOutputToContain('Format --month harus Y-m')
            ->assertFailed();

        $this->artisan('readings:audit', ['--from' => '2026-8-1'])
            ->expectsOutputToContain('Format --from harus Y-m')
            ->assertFailed();
    }

    public function test_rentang_bulan_terbalik_ditolak(): void
    {
        $this->artisan('readings:audit', ['--from' => '2026-08', '--to' => '2026-07'])
            ->expectsOutputToContain('--to tidak boleh sebelum --from')
            ->assertFailed();
    }

    public function test_audit_menemukan_agregat_yang_basi(): void
    {
        $this->agregasiPenuh();

        // Angka yang tidak lagi mencerminkan pembacaan mentahnya — persis yang
        // terjadi bila pembacaan datang terlambat setelah hari itu diringkas.
        MeterReadingDaily::where('power_meter_id', $this->meter->id)
            ->whereDate('date', '2026-08-03')
            ->update(['kwh_lwbp' => 999]);

        $this->artisan('readings:audit', ['--meter' => 'PM001', '--month' => '2026-08'])
            ->expectsOutputToContain('tidak cocok')
            ->assertSuccessful();
    }

    /**
     * Bulan yang pembacaan mentahnya sudah dibuang retensi tidak punya
     * pembanding — dan itu rancangannya, bukan kerusakan. Melaporkannya
     * sebagai temuan membuat setiap bulan lama muncul sebagai masalah palsu
     * dan menenggelamkan temuan yang sungguhan.
     */
    public function test_bulan_yang_pembacaan_mentahnya_sudah_dibuang_dilewati(): void
    {
        $this->agregasiPenuh();

        // Retensi membuang pembacaan mentah, agregat hariannya tetap.
        MeterReading::where('power_meter_id', $this->meter->id)->delete();

        $this->artisan('readings:audit', ['--meter' => 'PM001', '--month' => '2026-08'])
            ->expectsOutputToContain('dilewati')
            ->doesntExpectOutputToContain('tidak cocok')
            ->assertSuccessful();

        // Agregatnya tidak boleh ikut hilang.
        $this->assertSame(10, MeterReadingDaily::where('power_meter_id', $this->meter->id)->count());
    }

    public function test_agregasi_ulang_tidak_menghapus_agregat_yang_mentahnya_sudah_dibuang(): void
    {
        $this->agregasiPenuh();
        $lengkap = $this->sumHarian();

        MeterReading::where('power_meter_id', $this->meter->id)->delete();

        // Membangun ulang bulan tanpa pembacaan mentah harus TIDAK menyentuh
        // apa pun — kalau sampai menimpa dengan nol, riwayat lama hilang
        // permanen dan tidak bisa dibangun kembali.
        $this->artisan('readings:aggregate', ['--month' => '2026-08'])->assertSuccessful();

        $this->assertSame(10, MeterReadingDaily::where('power_meter_id', $this->meter->id)->count());
        $this->assertEqualsWithDelta($lengkap, $this->sumHarian(), 0.01);
    }
}
