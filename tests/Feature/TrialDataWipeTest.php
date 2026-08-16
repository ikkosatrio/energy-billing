<?php

namespace Tests\Feature;

use App\Livewire\System\TrialDataWipePage;
use App\Models\ActivityLog;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hapus data uji coba (pembacaan mentah + agregat harian) per rentang
 * tanggal bebas — beda dari hapus retensi karena agregat harian ikut
 * terhapus dan rentangnya tidak terikat cutoff.
 */
class TrialDataWipeTest extends TestCase
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

    public function test_menghapus_pembacaan_mentah_dan_agregat_harian_sekaligus(): void
    {
        $this->actingAs($this->admin());

        $meter = $this->meter();

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-01 08:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-08-10 08:00:00', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        MeterReadingDaily::create([
            'power_meter_id' => $meter->id, 'date' => '2026-08-01',
            'stand_lwbp_start' => 0, 'stand_lwbp_end' => 1, 'stand_wbp_start' => 0, 'stand_wbp_end' => 1,
            'kwh_lwbp' => 1, 'kwh_wbp' => 1, 'reading_count' => 48, 'reset_count' => 0,
        ]);

        Livewire::test(TrialDataWipePage::class)
            ->set('meterId', $meter->id)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-05')
            ->call('wipeNow');

        // Pembacaan 1 Agt & agregat harian 1 Agt terhapus, pembacaan 10 Agt (di luar rentang) tidak.
        $this->assertSame(1, MeterReading::count());
        $this->assertSame('2026-08-10 08:00:00', MeterReading::first()->read_at->toDateTimeString());
        $this->assertSame(0, MeterReadingDaily::count());

        $log = ActivityLog::where('action', 'wiped')->first();
        $this->assertNotNull($log);
        $this->assertSame(PowerMeter::class, $log->model_type);
        $this->assertStringContainsString($meter->code, $log->description);
    }

    public function test_hanya_meter_yang_dipilih_yang_terhapus(): void
    {
        $this->actingAs($this->admin());

        $meterA = $this->meter('MTR-A');
        $meterB = $this->meter('MTR-B');

        MeterReading::insert([
            ['power_meter_id' => $meterA->id, 'read_at' => '2026-08-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
            ['power_meter_id' => $meterB->id, 'read_at' => '2026-08-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api'],
        ]);

        Livewire::test(TrialDataWipePage::class)
            ->set('meterId', $meterA->id)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-01')
            ->call('wipeNow');

        $this->assertSame(0, MeterReading::where('power_meter_id', $meterA->id)->count());
        $this->assertSame(1, MeterReading::where('power_meter_id', $meterB->id)->count());
    }

    public function test_memerlukan_permission_reading_wipe_trial(): void
    {
        $viewer = User::create([
            'name' => 'Viewer', 'username' => 'viewer', 'email' => 'viewer@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', 'viewer')->value('id'),
        ]);

        $meter = $this->meter();
        MeterReading::create([
            'power_meter_id' => $meter->id, 'read_at' => '2026-08-01', 'stand_lwbp' => 1, 'stand_wbp' => 1, 'source' => 'api',
        ]);

        $this->actingAs($viewer);

        // Route halaman ditolak untuk role tanpa permission.
        $this->get(route('system.trial-data.index'))->assertForbidden();

        // Action Livewire juga ditolak, bukan cuma halamannya.
        Livewire::test(TrialDataWipePage::class)
            ->set('meterId', $meter->id)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-01')
            ->call('wipeNow')
            ->assertForbidden();

        $this->assertSame(1, MeterReading::count());
    }

    public function test_halaman_menampilkan_peringatan_invoice_yang_beririsan(): void
    {
        $this->actingAs($this->admin());

        $meter = $this->meter();
        $customer = Customer::create([
            'code' => 'CUST-TRIAL', 'power_meter_id' => $meter->id, 'name' => 'Pelanggan Uji', 'address' => 'Jl. Uji',
        ]);
        $period = BillingPeriod::create([
            'code' => '2026-08', 'period_start' => '2026-08-01',
            'period_end' => '2026-08-31', 'cut_off_date' => '2026-09-01',
        ]);

        Invoice::create([
            'invoice_no' => 'INV-TRIAL-001',
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'power_meter_id' => $meter->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'issue_date' => '2026-09-01',
            'total_amount' => 100000,
            'status' => 'issued',
        ]);

        Livewire::test(TrialDataWipePage::class)
            ->set('meterId', $meter->id)
            ->set('from', '2026-08-05')
            ->set('to', '2026-08-10')
            ->assertSee('1 invoice')
            ->assertSee('INV-TRIAL-001');
    }

    public function test_tidak_ada_data_pada_rentang_menampilkan_toast_info_tanpa_mencatat_log(): void
    {
        $this->actingAs($this->admin());

        $meter = $this->meter();

        Livewire::test(TrialDataWipePage::class)
            ->set('meterId', $meter->id)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-01')
            ->call('wipeNow');

        $this->assertNull(ActivityLog::where('action', 'wiped')->first());
    }
}
