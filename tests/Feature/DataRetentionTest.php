<?php

namespace Tests\Feature;

use App\Livewire\Report\ReadingReportPage;
use App\Models\ActivityLog;
use App\Models\MeterReading;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use App\Services\Monitoring\DataRetentionService;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Retensi & penghapusan pembacaan mentah — jadwal mingguan (seluruh meter)
 * dan hapus manual per meter dari halaman Data Meter Mentah. Keduanya
 * memakai DataRetentionService yang sama supaya cutoff-nya selalu konsisten.
 */
class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        Carbon::setTestNow('2026-08-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function meter(string $code = 'MTR-01'): PowerMeter
    {
        return PowerMeter::create(['code' => $code, 'name' => 'Panel', 'multiplier' => 1, 'status' => 'active']);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    // ── DataRetentionService ─────────────────────────────────────────────

    public function test_rangefor_mengembalikan_rentang_sesungguhnya_bukan_hasil_filter(): void
    {
        $meter = $this->meter();

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-01-03 08:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-15 10:00:00', 'stand_lwbp' => 2, 'stand_wbp' => 2, 'source' => 'api'],
        ]);

        $range = app(DataRetentionService::class)->rangeFor($meter);

        $this->assertSame(2, $range['count']);
        $this->assertSame('2026-01-03 08:00:00', $range['first_at']->toDateTimeString());
        $this->assertSame('2026-08-15 10:00:00', $range['last_at']->toDateTimeString());
    }

    public function test_wouldpurgecount_hanya_menghitung_yang_lewat_cutoff(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $meter = $this->meter();

        MeterReading::insert([
            // Lewat cutoff (6 bulan sebelum 15 Agt 2026 = 15 Feb 2026).
            ['power_meter_id' => $meter->id, 'read_at' => '2026-01-01 00:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-01-02 00:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            // Masih dalam masa retensi.
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-01 00:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        $this->assertSame(2, app(DataRetentionService::class)->wouldPurgeCount($meter));
    }

    public function test_retensi_kosong_tidak_ada_yang_dianggap_lewat_cutoff(): void
    {
        app(SettingService::class)->put('iot_retention_months', 0);
        $meter = $this->meter();

        MeterReading::create([
            'power_meter_id' => $meter->id, 'read_at' => '2020-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api',
        ]);

        $this->assertSame(0, app(DataRetentionService::class)->wouldPurgeCount($meter));
        $this->assertSame(0, app(DataRetentionService::class)->purge($meter->id));
    }

    public function test_purge_hanya_menghapus_meter_yang_diminta(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $meterA = $this->meter('MTR-A');
        $meterB = $this->meter('MTR-B');

        MeterReading::insert([
            ['power_meter_id' => $meterA->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meterB->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        $deleted = app(DataRetentionService::class)->purge($meterA->id);

        $this->assertSame(1, $deleted);
        $this->assertSame(0, MeterReading::where('power_meter_id', $meterA->id)->count());
        $this->assertSame(1, MeterReading::where('power_meter_id', $meterB->id)->count());
    }

    public function test_purge_tanpa_meter_id_menghapus_seluruh_meter(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $meterA = $this->meter('MTR-A');
        $meterB = $this->meter('MTR-B');

        MeterReading::insert([
            ['power_meter_id' => $meterA->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meterB->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        $deleted = app(DataRetentionService::class)->purge();

        $this->assertSame(2, $deleted);
        $this->assertSame(0, MeterReading::count());
    }

    // ── Jadwal mingguan (readings:prune) ─────────────────────────────────

    public function test_jadwal_mingguan_menghapus_lewat_retensi_dan_mencatat_log(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $meter = $this->meter();

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        $this->artisan('readings:prune')->assertSuccessful();

        $this->assertSame(1, MeterReading::count());
        $this->assertSame('2026-08-01 00:00:00', MeterReading::first()->read_at->toDateTimeString());

        $log = ActivityLog::where('action', 'pruned')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('1 pembacaan', $log->description);
    }

    public function test_jadwal_mingguan_memakai_opsi_months_bila_diberikan(): void
    {
        app(SettingService::class)->put('iot_retention_months', 24); // seharusnya diabaikan
        $meter = $this->meter();

        MeterReading::create([
            'power_meter_id' => $meter->id, 'read_at' => '2026-06-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api',
        ]);

        // --months=1 → cutoff 15 Juli 2026, baris Juni ikut terhapus walau
        // setting sistemnya 24 bulan.
        $this->artisan('readings:prune', ['--months' => 1])->assertSuccessful();

        $this->assertSame(0, MeterReading::count());
    }

    public function test_jadwal_mingguan_tidak_menghapus_apa_pun_bila_retensi_dimatikan(): void
    {
        app(SettingService::class)->put('iot_retention_months', 0);
        $meter = $this->meter();

        MeterReading::create([
            'power_meter_id' => $meter->id, 'read_at' => '2000-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api',
        ]);

        $this->artisan('readings:prune')->assertSuccessful();

        $this->assertSame(1, MeterReading::count());
    }

    // ── Hapus manual per meter (halaman Data Meter Mentah) ───────────────

    public function test_hapus_manual_memerlukan_permission_reading_purge(): void
    {
        // Viewer tidak diberi reading.purge secara default.
        $viewer = User::create([
            'name' => 'Viewer', 'username' => 'viewer', 'email' => 'viewer@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', 'viewer')->value('id'),
        ]);

        $meter = $this->meter();
        MeterReading::create([
            'power_meter_id' => $meter->id, 'read_at' => '2020-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api',
        ]);

        $this->actingAs($viewer);

        Livewire::test(ReadingReportPage::class)
            ->set('meterId', $meter->id)
            ->call('purgeNow')
            ->assertForbidden();

        $this->assertSame(1, MeterReading::count());
    }

    public function test_hapus_manual_super_admin_menghapus_dan_mencatat_log(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $this->actingAs($this->admin());

        $meter = $this->meter();
        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        Livewire::test(ReadingReportPage::class)
            ->set('meterId', $meter->id)
            ->call('purgeNow');

        $this->assertSame(1, MeterReading::count());

        $log = ActivityLog::where('action', 'pruned')->first();
        $this->assertNotNull($log);
        $this->assertSame(PowerMeter::class, $log->model_type);
        $this->assertSame($meter->id, $log->model_id);
        $this->assertStringContainsString($meter->code, $log->description);
    }

    public function test_hapus_manual_hanya_meter_yang_dipilih_di_halaman(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $this->actingAs($this->admin());

        $meterA = $this->meter('MTR-A');
        $meterB = $this->meter('MTR-B');

        MeterReading::insert([
            ['power_meter_id' => $meterA->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meterB->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        Livewire::test(ReadingReportPage::class)
            ->set('meterId', $meterA->id)
            ->call('purgeNow');

        $this->assertSame(0, MeterReading::where('power_meter_id', $meterA->id)->count());
        $this->assertSame(1, MeterReading::where('power_meter_id', $meterB->id)->count());
    }

    public function test_halaman_menampilkan_panel_retensi(): void
    {
        app(SettingService::class)->put('iot_retention_months', 6);
        $this->actingAs($this->admin());

        $meter = $this->meter();
        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-01-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        Livewire::test(ReadingReportPage::class)
            ->set('meterId', $meter->id)
            ->assertSee('Retensi Data Mentah')
            ->assertViewHas('retentionRange', fn ($range) => $range['count'] === 2)
            ->assertViewHas('wouldPurgeCount', 1);
    }
}
