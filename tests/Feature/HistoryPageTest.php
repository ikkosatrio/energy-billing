<?php

namespace Tests\Feature;

use App\Livewire\Monitoring\HistoryPage;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Riwayat Per Hari di Monitoring → Energy History.
 *
 * Fokus: nilai LWBP/WBP/Total tampil sebagai angka (bukan cuma tersirat dari
 * tinggi batang), dan "data tidak lengkap" tidak lagi salah menyala untuk
 * meter yang push-nya bukan tiap 1 menit (lihat MeterReadingDaily).
 */
class HistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->actingAs(User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]));
    }

    private function meter(): PowerMeter
    {
        return PowerMeter::create(['code' => 'MTR-01', 'name' => 'Panel Satu', 'multiplier' => 1, 'status' => 'active']);
    }

    public function test_riwayat_per_hari_menampilkan_lwbp_wbp_dan_total(): void
    {
        $meter = $this->meter();

        MeterReadingDaily::create([
            'power_meter_id' => $meter->id, 'date' => '2026-08-07',
            'kwh_lwbp' => 300, 'kwh_wbp' => 82, 'reading_count' => 48,
        ]);

        Livewire::test(HistoryPage::class)
            ->set('meterId', $meter->id)
            ->set('month', '2026-08')
            ->assertSee('300,0')
            ->assertSee('82,0')
            ->assertSee('382,0');
    }

    public function test_baris_total_menjumlahkan_seluruh_hari_dalam_bulan(): void
    {
        $meter = $this->meter();

        MeterReadingDaily::create(['power_meter_id' => $meter->id, 'date' => '2026-08-01', 'kwh_lwbp' => 100, 'kwh_wbp' => 20, 'reading_count' => 48]);
        MeterReadingDaily::create(['power_meter_id' => $meter->id, 'date' => '2026-08-02', 'kwh_lwbp' => 150, 'kwh_wbp' => 30, 'reading_count' => 48]);

        Livewire::test(HistoryPage::class)
            ->set('meterId', $meter->id)
            ->set('month', '2026-08')
            ->assertViewHas('summary', fn ($summary) => $summary['lwbp'] === 250.0
                && $summary['wbp'] === 50.0
                && $summary['total'] === 300.0);
    }

    // ── Akar masalah: ambang kelengkapan mengikuti interval yang dikonfigurasi ──

    public function test_hari_penuh_pada_interval_30_menit_tidak_lagi_dianggap_tidak_lengkap(): void
    {
        app(SettingService::class)->put('iot_push_interval_seconds', 1800); // 30 menit → 48x/hari

        $daily = new MeterReadingDaily(['reading_count' => 48]);

        $this->assertFalse($daily->is_incomplete);
    }

    public function test_gateway_yang_benar_benar_sempat_offline_tetap_terdeteksi(): void
    {
        app(SettingService::class)->put('iot_push_interval_seconds', 1800);

        // Cuma 20 dari 48 yang seharusnya — gateway mati separuh hari.
        $daily = new MeterReadingDaily(['reading_count' => 20]);

        $this->assertTrue($daily->is_incomplete);
    }

    public function test_penanda_data_tidak_lengkap_tidak_lagi_ditampilkan(): void
    {
        $meter = $this->meter();

        // 48 baris sehari (interval 30 menit) dulu selalu ditandai tidak
        // lengkap karena ambangnya hardcode 1.440 (asumsi interval 1 menit).
        app(SettingService::class)->put('iot_push_interval_seconds', 1800);
        MeterReadingDaily::create([
            'power_meter_id' => $meter->id, 'date' => '2026-08-07',
            'kwh_lwbp' => 300, 'kwh_wbp' => 82, 'reading_count' => 48,
        ]);

        Livewire::test(HistoryPage::class)
            ->set('meterId', $meter->id)
            ->set('month', '2026-08')
            ->assertDontSee('data tidak lengkap')
            ->assertDontSeeHtml('opacity:.45')
            ->assertDontSeeHtml('opacity:0.45');
    }
}
