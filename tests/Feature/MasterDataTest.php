<?php

namespace Tests\Feature;

use App\Livewire\Master\CustomerPage;
use App\Livewire\Master\PowerMeterPage;
use App\Livewire\Tariff\TariffGroupPage;
use App\Livewire\Tariff\TariffSchedulePage;
use App\Models\Customer;
use App\Models\MeterTariffSchedule;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\TariffGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->actingAs($this->superAdmin());
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@test.local',
            'password' => 'secret123',
            'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    public function test_power_meter_dibuat_tanpa_rahasia_per_perangkat(): void
    {
        Livewire::test(PowerMeterPage::class)
            ->call('create')
            ->set('form.code', 'MTR-01')
            ->set('form.name', 'LVMDP 01')
            ->set('form.multiplier', 1)
            ->set('form.status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $meter = PowerMeter::first();

        $this->assertSame('MTR-01', $meter->code);
        // ID inilah yang dipakai gateway sebagai meter_id, dan selalu terlihat.
        $this->assertNotNull($meter->id);
        $this->assertArrayNotHasKey('device_key', $meter->getAttributes());
    }

    public function test_kode_meter_tidak_boleh_duplikat(): void
    {
        PowerMeter::create(['code' => 'MTR-01', 'name' => 'A', 'multiplier' => 1]);

        Livewire::test(PowerMeterPage::class)
            ->call('create')
            ->set('form.code', 'MTR-01')
            ->set('form.name', 'B')
            ->set('form.multiplier', 1)
            ->set('form.status', 'active')
            ->call('save')
            ->assertHasErrors(['form.code' => 'unique']);
    }

    public function test_satu_meter_tidak_bisa_dipakai_dua_pelanggan(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-01', 'name' => 'A', 'multiplier' => 1]);
        $group = TariffGroup::create(['code' => 'I-3/TR', 'name' => 'I-3']);

        Customer::create([
            'code' => 'C-001', 'name' => 'Pelanggan A',
            'power_meter_id' => $meter->id, 'tariff_group_id' => $group->id,
        ]);

        Livewire::test(CustomerPage::class)
            ->call('create')
            ->set('form.code', 'C-002')
            ->set('form.name', 'Pelanggan B')
            ->set('form.power_meter_id', $meter->id)
            ->set('form.biaya_beban_mode', 'flat')
            ->set('form.status', 'active')
            ->call('save')
            ->assertHasErrors(['form.power_meter_id' => 'unique']);
    }

    public function test_tanggal_tagih_di_luar_1_sampai_28_ditolak(): void
    {
        Livewire::test(CustomerPage::class)
            ->call('create')
            ->set('form.code', 'C-001')
            ->set('form.name', 'Pelanggan A')
            ->set('form.billing_day', 31)
            ->set('form.biaya_beban_mode', 'flat')
            ->set('form.status', 'active')
            ->call('save')
            ->assertHasErrors(['form.billing_day']);
    }

    public function test_mode_per_kva_menolkan_biaya_beban_flat(): void
    {
        Livewire::test(CustomerPage::class)
            ->call('create')
            ->set('form.code', 'C-001')
            ->set('form.name', 'Pelanggan A')
            ->set('form.daya_kva', 630)
            ->set('form.biaya_beban_mode', 'per_kva')
            ->set('form.biaya_beban', 9_450_000)
            ->set('form.status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::first();

        // Nilai flat sisa dari mode sebelumnya tidak boleh ikut terhitung.
        $this->assertEquals(0, $customer->biaya_beban);
        $this->assertEquals(630, $customer->daya_kva);
    }

    public function test_pelanggan_yang_sudah_punya_invoice_tidak_bisa_dihapus(): void
    {
        $customer = Customer::create(['code' => 'C-001', 'name' => 'Pelanggan A']);

        $period = \App\Models\BillingPeriod::create([
            'code' => '2026-08',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'cut_off_date' => '2026-09-01',
        ]);

        \App\Models\Invoice::create([
            'invoice_no' => 'INV/2026/08/001',
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        Livewire::test(CustomerPage::class)->call('delete', $customer->id);

        $this->assertNotNull(Customer::find($customer->id));
    }

    public function test_tarif_baru_menutup_tarif_lama_tanpa_mengubah_nilainya(): void
    {
        $group = TariffGroup::create(['code' => 'I-3/TR', 'name' => 'I-3']);
        $old = $group->rates()->create([
            'rate_lwbp' => 1000, 'rate_wbp' => 1400,
            'effective_from' => '2026-01-01', 'effective_to' => null,
        ]);

        Livewire::test(TariffGroupPage::class)
            ->call('newRate', $group->id)
            ->set('rateForm.rate_lwbp', 1114.74)
            ->set('rateForm.rate_wbp', 1560.64)
            ->set('rateForm.effective_from', '2026-07-01')
            ->call('saveRate')
            ->assertHasNoErrors();

        $old->refresh();

        // Tarif lama hanya ditutup masa berlakunya, angkanya tetap.
        $this->assertEquals(1000, $old->rate_lwbp);
        $this->assertSame('2026-06-30', $old->effective_to->toDateString());

        // Tarif yang berlaku pada tanggal setelahnya adalah tarif baru.
        $this->assertEquals(1114.74, $group->rateOn('2026-08-31')->rate_lwbp);
        // Sedangkan tanggal sebelum pergantian tetap memakai tarif lama.
        $this->assertEquals(1000, $group->rateOn('2026-06-30')->rate_lwbp);
    }

    public function test_jadwal_wbp_lwbp_tersimpan_dan_tersambung(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-01', 'name' => 'A', 'multiplier' => 1]);

        Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $meter->id)
            ->set('periods', [
                ['start_time' => '22:00', 'tariff_type' => 'LWBP'],
                ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
                ['start_time' => '17:00', 'tariff_type' => 'WBP'],
            ])
            ->call('save');

        $rows = MeterTariffSchedule::where('power_meter_id', $meter->id)->orderBy('sequence')->get();

        $this->assertCount(3, $rows);
        // Disimpan terurut walau diinput acak.
        $this->assertSame('00:00:00', $rows[0]->start_time);
        $this->assertSame('17:00:00', $rows[0]->end_time);
        $this->assertSame('WBP', $rows[1]->tariff_type);
    }

    public function test_jadwal_tidak_valid_tidak_tersimpan(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-01', 'name' => 'A', 'multiplier' => 1]);

        Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $meter->id)
            ->set('periods', [
                ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
                ['start_time' => '17:07', 'tariff_type' => 'WBP'],
            ])
            ->call('save');

        $this->assertSame(0, MeterTariffSchedule::where('power_meter_id', $meter->id)->count());
    }

    public function test_jadwal_bisa_diduplikat_dari_meter_lain(): void
    {
        $sumber = PowerMeter::create(['code' => 'MTR-01', 'name' => 'Sumber', 'multiplier' => 1]);
        $tujuan = PowerMeter::create(['code' => 'MTR-02', 'name' => 'Tujuan', 'multiplier' => 1]);

        Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $sumber->id)
            ->set('periods', [
                ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
                ['start_time' => '18:00', 'tariff_type' => 'WBP'],
                ['start_time' => '23:00', 'tariff_type' => 'LWBP'],
            ])
            ->call('save');

        $component = Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $tujuan->id)
            ->call('copyFrom', $sumber->id);

        // Masuk ke form dulu, belum tersimpan — pengguna masih bisa memeriksa.
        $component->assertSet('periods', [
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '18:00', 'tariff_type' => 'WBP'],
            ['start_time' => '23:00', 'tariff_type' => 'LWBP'],
        ]);
        $this->assertSame(0, MeterTariffSchedule::where('power_meter_id', $tujuan->id)->count());

        $component->call('save');

        $this->assertSame(3, MeterTariffSchedule::where('power_meter_id', $tujuan->id)->count());
    }

    public function test_duplikat_dari_meter_tanpa_jadwal_ditolak(): void
    {
        $kosong = PowerMeter::create(['code' => 'MTR-01', 'name' => 'Kosong', 'multiplier' => 1]);
        $tujuan = PowerMeter::create(['code' => 'MTR-02', 'name' => 'Tujuan', 'multiplier' => 1]);

        Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $tujuan->id)
            ->set('periods', [['start_time' => '00:00', 'tariff_type' => 'WBP']])
            ->call('copyFrom', $kosong->id)
            // Form tidak ikut terhapus saat sumbernya kosong.
            ->assertSet('periods', [['start_time' => '00:00', 'tariff_type' => 'WBP']]);
    }

    public function test_duplikat_ditolak_tanpa_izin_ubah_tarif(): void
    {
        $sumber = PowerMeter::create(['code' => 'MTR-01', 'name' => 'Sumber', 'multiplier' => 1]);
        $tujuan = PowerMeter::create(['code' => 'MTR-02', 'name' => 'Tujuan', 'multiplier' => 1]);

        Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $sumber->id)
            ->set('periods', [
                ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
                ['start_time' => '18:00', 'tariff_type' => 'WBP'],
            ])
            ->call('save');

        $viewer = User::create([
            'name' => 'Viewer', 'username' => 'viewer', 'email' => 'viewer@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', 'viewer')->value('id'),
        ]);

        $this->actingAs($viewer);

        Livewire::test(TariffSchedulePage::class)
            ->set('meterId', $tujuan->id)
            ->call('copyFrom', $sumber->id)
            ->assertForbidden();
    }

    public function test_user_tanpa_izin_tidak_bisa_membuat_pelanggan(): void
    {
        $viewer = User::create([
            'name' => 'Viewer', 'username' => 'viewer', 'email' => 'viewer@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', 'viewer')->value('id'),
        ]);

        $this->actingAs($viewer);

        Livewire::test(CustomerPage::class)
            ->call('create')
            ->assertForbidden();
    }
}
