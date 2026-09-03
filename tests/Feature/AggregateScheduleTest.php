<?php

namespace Tests\Feature;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AggregateScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        Carbon::setTestNow('2026-08-14 09:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function meterWithReadings(): PowerMeter
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-01',
            'name' => 'LVMDP 01',
            'multiplier' => 1,
            'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 00:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 400, 'active_power_kw' => 100, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-13 23:00:00', 'stand_lwbp' => 1100, 'stand_wbp' => 420, 'active_power_kw' => 120, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-14 09:00:00', 'stand_lwbp' => 1150, 'stand_wbp' => 430, 'active_power_kw' => 140, 'source' => 'api'],
        ]);

        return $meter;
    }

    public function test_today_hanya_meringkas_hari_ini(): void
    {
        $this->meterWithReadings();

        $this->artisan('readings:aggregate --today')->assertSuccessful();

        $this->assertSame(['2026-08-14'], MeterReadingDaily::pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all());
    }

    public function test_tanpa_opsi_kemarin_ikut_diringkas(): void
    {
        $this->meterWithReadings();

        $this->artisan('readings:aggregate')->assertSuccessful();

        $this->assertSame(['2026-08-13', '2026-08-14'], MeterReadingDaily::orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all());
    }

    /**
     * Jadwal per menit harus tetap satu tanggal. Kalau --today hilang, tiap
     * menit ikut menghitung ulang kemarin — bebannya dobel tanpa manfaat,
     * karena itu tugas pass per jam.
     */
    public function test_agregasi_dijadwalkan_per_menit_untuk_hari_ini(): void
    {
        $byExpression = $this->jadwalAgregasi();

        $this->assertStringContainsString('--today', $byExpression['* * * * *']);
        $this->assertStringNotContainsString('--today', $byExpression['0 * * * *']);
    }

    /**
     * Pengejaran mingguan wajib ada.
     *
     * Dua jadwal lain hanya mencakup kemarin dan hari ini, jadi gangguan yang
     * lebih lama dari sehari meninggalkan hari tanpa agregat yang tidak pernah
     * terkejar sendiri — dan chart bulanan akan terus membaca lebih rendah
     * daripada invoice untuk bulan itu tanpa ada yang memberi tahu.
     */
    public function test_ada_pengejaran_mingguan_ke_belakang(): void
    {
        $byExpression = $this->jadwalAgregasi();

        $this->assertArrayHasKey('0 3 * * 0', $byExpression, 'Pengejaran mingguan tidak terdaftar.');

        $mingguan = $byExpression['0 3 * * 0'];

        $this->assertStringContainsString('--from', $mingguan);
        $this->assertStringNotContainsString('--today', $mingguan);

        // Rentangnya harus melampaui sebulan supaya bulan sebelumnya masih
        // ikut terkoreksi setelah invoicenya terbit.
        preg_match("/--from='?(\d{4}-\d{2}-\d{2})'?/", $mingguan, $cocok);
        $this->assertNotEmpty($cocok, "Tanggal --from tidak terbaca dari: {$mingguan}");
        $this->assertGreaterThanOrEqual(
            35,
            Carbon::parse($cocok[1])->diffInDays(Carbon::today()),
            'Rentang pengejaran terlalu pendek untuk menutup bulan sebelumnya.',
        );
    }

    /** @return array<string, string> ekspresi cron => perintah */
    private function jadwalAgregasi(): array
    {
        return collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'readings:aggregate'))
            ->mapWithKeys(fn ($event) => [$event->expression => $event->command])
            ->all();
    }
}
