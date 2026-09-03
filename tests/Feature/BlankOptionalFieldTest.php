<?php

namespace Tests\Feature;

use App\Livewire\Master\CustomerPage;
use App\Livewire\Master\PowerMeterPage;
use App\Models\Customer;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\TariffGroup;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kolom opsional yang dikosongkan di form.
 *
 * Input tanggal dan angka yang dihapus isinya mengirim STRING KOSONG, bukan
 * null, dan aturan `nullable` meloloskannya apa adanya sampai ke database —
 * yang menolaknya dengan "Incorrect date value: ''". Di produksi APP_DEBUG
 * mati, jadi pengguna hanya melihat halaman error tanpa petunjuk apa pun.
 *
 * Test ini memakai komponen Livewire-nya langsung, bukan model, karena string
 * kosong itu justru lahir di lapisan form.
 */
class BlankOptionalFieldTest extends TestCase
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

    public function test_pelanggan_tersimpan_saat_tanggal_kontrak_dikosongkan(): void
    {
        $meter = PowerMeter::create(['code' => 'MTR-01', 'name' => 'LVMDP', 'multiplier' => 1, 'status' => 'active']);
        $group = TariffGroup::create(['code' => 'I-3/TR', 'name' => 'I-3 / TR']);

        Livewire::test(CustomerPage::class)
            ->call('create')
            ->set('form.code', 'C-001')
            ->set('form.name', 'PT Pelanggan Uji')
            ->set('form.power_meter_id', $meter->id)
            ->set('form.tariff_group_id', $group->id)
            ->set('form.contract_start', '')
            ->set('form.contract_end', '')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::firstOrFail();

        $this->assertNull($customer->contract_start);
        $this->assertNull($customer->contract_end);
    }

    /** Kolom angka opsional lain pada form yang sama. */
    public function test_pelanggan_tersimpan_saat_angka_opsional_dikosongkan(): void
    {
        Livewire::test(CustomerPage::class)
            ->call('create')
            ->set('form.code', 'C-002')
            ->set('form.name', 'PT Pelanggan Dua')
            ->set('form.billing_day', '')
            ->set('form.daya_kva', '')
            ->set('form.biaya_beban', '')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::firstOrFail();

        $this->assertNull($customer->billing_day);
        // daya_kva dan biaya_beban NOT NULL berdefault 0 — dikosongkan
        // berarti nol, bukan "tidak diisi".
        $this->assertEquals(0, $customer->daya_kva);
        $this->assertEquals(0, $customer->biaya_beban);
    }

    public function test_power_meter_tersimpan_saat_tanggal_pasang_dikosongkan(): void
    {
        Livewire::test(PowerMeterPage::class)
            ->call('create')
            ->set('form.code', 'MTR-99')
            ->set('form.name', 'Panel Uji')
            ->set('form.multiplier', 1)
            ->set('form.installed_at', '')
            ->set('form.stand_max', '')
            ->call('save')
            ->assertHasNoErrors();

        $meter = PowerMeter::where('code', 'MTR-99')->firstOrFail();

        $this->assertNull($meter->installed_at);
        $this->assertNull($meter->stand_max);
    }

    /** Nilai yang diisi tetap tersimpan — perbaikan ini tidak boleh menelan data. */
    public function test_tanggal_yang_diisi_tetap_tersimpan(): void
    {
        Livewire::test(CustomerPage::class)
            ->call('create')
            ->set('form.code', 'C-003')
            ->set('form.name', 'PT Pelanggan Tiga')
            ->set('form.contract_start', '2026-09-01')
            ->set('form.contract_end', '2026-12-31')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::firstOrFail();

        $this->assertSame('2026-09-01', $customer->contract_start->toDateString());
        $this->assertSame('2026-12-31', $customer->contract_end->toDateString());
    }

    /** Kolom teks tidak ikut diubah jadi null — string kosong di sana sah. */
    public function test_kolom_teks_kosong_tidak_berubah_jadi_null(): void
    {
        $customer = Customer::create([
            'code' => 'C-004', 'name' => 'PT Empat', 'status' => 'active', 'address' => '',
        ]);

        $this->assertSame('', $customer->fresh()->address);
    }
}
