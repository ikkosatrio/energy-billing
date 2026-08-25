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
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'readings:aggregate'));

        $byExpression = $events->mapWithKeys(fn ($event) => [$event->expression => $event->command]);

        $this->assertCount(2, $events);
        $this->assertStringContainsString('--today', $byExpression['* * * * *']);
        $this->assertStringNotContainsString('--today', $byExpression['0 * * * *']);
    }
}
