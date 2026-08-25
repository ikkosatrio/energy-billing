<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\Permission;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Fondasi portal pelanggan: pemisahan guard, role & permission per guard,
 * serta daftar akses per akun.
 *
 * Yang dijaga di sini bukan tampilan, tapi batas yang kalau bocor berakibat
 * paling parah: akun portal menembus panel admin, atau melihat data pelanggan
 * yang bukan miliknya.
 */
class PortalAuthFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    private function customer(string $code = 'CUST-01', ?PowerMeter $meter = null): Customer
    {
        return Customer::create([
            'code' => $code,
            'name' => 'Pelanggan '.$code,
            'power_meter_id' => $meter?->id,
        ]);
    }

    private function meter(string $code = 'MTR-01'): PowerMeter
    {
        return PowerMeter::create(['code' => $code, 'name' => 'Panel '.$code, 'multiplier' => 1, 'status' => 'active']);
    }

    private function portalUser(string $roleSlug = 'portal-penuh', string $username = 'pelanggan'): CustomerUser
    {
        return CustomerUser::create([
            'name' => 'Akun '.$username,
            'username' => $username,
            'password' => 'secret123',
            'role_id' => Role::portal()->where('slug', $roleSlug)->value('id'),
        ]);
    }

    private function staffAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    // ── Pemisahan role & permission per guard ────────────────────────────

    public function test_role_dan_permission_portal_terpisah_dari_staf(): void
    {
        $this->assertSame(4, Permission::portal()->count());
        $this->assertSame(2, Role::portal()->count());

        // Tidak ada satu pun permission staf yang ikut ke role portal.
        foreach (Role::portal()->with('permissions')->get() as $role) {
            foreach ($role->permissions as $permission) {
                $this->assertSame(CustomerUser::GUARD, $permission->guard,
                    "Permission {$permission->slug} bocor ke role portal {$role->slug}.");
            }
        }

        // Dan sebaliknya.
        foreach (Role::staff()->with('permissions')->get() as $role) {
            foreach ($role->permissions as $permission) {
                $this->assertSame('web', $permission->guard,
                    "Permission portal {$permission->slug} bocor ke role staf {$role->slug}.");
            }
        }
    }

    public function test_role_portal_tidak_pernah_dianggap_super_admin(): void
    {
        // Slug 'super-admin' pada guard portal tetap BUKAN super admin: slug
        // unique saja tidak menjamin baris seperti ini tak pernah ada (bisa
        // masuk lewat impor atau seeder pihak lain), jadi pemeriksaan guard
        // di isSuperAdmin() yang menjadi penahannya.
        $palsu = new Role(['name' => 'Palsu', 'slug' => Role::SUPER_ADMIN, 'guard' => CustomerUser::GUARD]);

        $this->assertFalse($palsu->isSuperAdmin());
        $this->assertTrue(Role::where('slug', Role::SUPER_ADMIN)->first()->isSuperAdmin());
    }

    public function test_akun_portal_tanpa_role_tidak_punya_permission_apa_pun(): void
    {
        $akun = CustomerUser::create([
            'name' => 'Tanpa Role', 'username' => 'tanparole', 'password' => 'secret123',
        ]);

        $this->assertFalse($akun->hasPermission('portal.invoice.view'));
        $this->assertFalse($akun->hasPermission('user.delete'));
        $this->assertFalse($akun->hasAnyPermission(['portal.invoice.view']));
        // Daftar kosong tetap lolos — dipakai menu tanpa pembatasan.
        $this->assertTrue($akun->hasAnyPermission([]));
    }

    public function test_role_pantau_saja_tidak_bisa_lihat_invoice(): void
    {
        $akun = $this->portalUser('portal-pantau');

        $this->assertTrue($akun->hasPermission('portal.monitoring.view'));
        $this->assertTrue($akun->hasPermission('portal.usage.view'));
        $this->assertFalse($akun->hasPermission('portal.invoice.view'));
        $this->assertFalse($akun->hasPermission('portal.payment.view'));
    }

    public function test_akun_portal_tidak_punya_permission_staf(): void
    {
        $akun = $this->portalUser('portal-penuh');

        foreach (['user.delete', 'invoice.generate', 'setting.manage', 'role.manage'] as $slug) {
            $this->assertFalse($akun->hasPermission($slug),
                "Akun portal tidak boleh punya permission staf {$slug}.");
        }
    }

    // ── Gate::before lintas guard ────────────────────────────────────────

    public function test_gate_menilai_akun_portal_tanpa_typeerror(): void
    {
        $akun = $this->portalUser('portal-penuh');

        // Sebelum perbaikan, closure Gate::before di-hint App\Models\User dan
        // baris ini mati dengan TypeError sebelum sempat menjawab.
        $this->assertTrue(Gate::forUser($akun)->allows('portal.invoice.view'));
        $this->assertFalse(Gate::forUser($akun)->allows('user.delete'));
    }

    public function test_gate_tetap_meloloskan_super_admin_staf(): void
    {
        $this->assertTrue(Gate::forUser($this->staffAdmin())->allows('setting.manage'));
    }

    // ── Daftar akses per akun ────────────────────────────────────────────

    public function test_akun_hanya_mengakses_pelanggan_yang_diassign(): void
    {
        $meterA = $this->meter('MTR-A');
        $meterB = $this->meter('MTR-B');
        $milik = $this->customer('CUST-A', $meterA);
        $orangLain = $this->customer('CUST-B', $meterB);

        $akun = $this->portalUser();
        $akun->customers()->attach($milik->id);

        $this->assertSame([$milik->id], $akun->accessibleCustomerIds());
        $this->assertTrue($akun->canAccessCustomer($milik->id));
        $this->assertFalse($akun->canAccessCustomer($orangLain->id));
        $this->assertFalse($akun->canAccessCustomer(null));
    }

    public function test_daftar_meter_diturunkan_dari_pelanggan_yang_diassign(): void
    {
        $meterA = $this->meter('MTR-A');
        $meterB = $this->meter('MTR-B');
        $pelangganA = $this->customer('CUST-A', $meterA);
        $this->customer('CUST-B', $meterB);
        // Pelanggan tanpa meter terpasang — tidak boleh menghasilkan null di
        // daftar, karena whereIn dengan null membuat kondisinya tidak pernah
        // terpenuhi dan seluruh data ikut hilang.
        $tanpaMeter = $this->customer('CUST-C');

        $akun = $this->portalUser();
        $akun->customers()->attach([$pelangganA->id, $tanpaMeter->id]);

        $this->assertSame([$meterA->id], $akun->accessibleMeterIds());
        $this->assertTrue($akun->canAccessMeter($meterA->id));
        // Meter pelanggan lain tidak ikut terbawa walau ada di database.
        $this->assertFalse($akun->canAccessMeter($meterB->id));
        $this->assertNotContains(null, $akun->accessibleMeterIds());
    }

    public function test_satu_akun_boleh_mengakses_beberapa_pelanggan(): void
    {
        $satu = $this->customer('CUST-1', $this->meter('MTR-1'));
        $dua = $this->customer('CUST-2', $this->meter('MTR-2'));
        $tiga = $this->customer('CUST-3', $this->meter('MTR-3'));

        $akun = $this->portalUser();
        $akun->customers()->attach([$satu->id, $dua->id, $tiga->id]);

        $this->assertCount(3, $akun->accessibleCustomerIds());
        $this->assertCount(3, $akun->accessibleMeterIds());
    }

    // ── Isolasi tabel staf ──────────────────────────────────────────────

    public function test_akun_portal_tidak_muncul_di_tabel_user_staf(): void
    {
        $this->portalUser();

        $this->assertSame(0, User::count());
        $this->assertSame(1, CustomerUser::count());
    }

    public function test_role_portal_tidak_bisa_diberikan_ke_user_staf(): void
    {
        $portalRoleId = Role::portal()->value('id');

        $this->actingAs($this->staffAdmin());

        \Livewire\Livewire::test(\App\Livewire\System\UserPage::class)
            ->call('create')
            ->set('form.name', 'Staf Uji')
            ->set('form.username', 'stafuji')
            ->set('form.email', 'stafuji@test.local')
            ->set('form.password', 'Secret123!')
            ->set('form.password_confirmation', 'Secret123!')
            ->set('form.role_id', $portalRoleId)
            ->call('save')
            ->assertHasErrors('form.role_id');

        $this->assertNull(User::where('username', 'stafuji')->first());
    }

    // ── Jejak audit ─────────────────────────────────────────────────────

    public function test_aksi_portal_dicatat_di_kolom_customer_user_bukan_user(): void
    {
        $akun = $this->portalUser();

        $this->actingAs($akun, CustomerUser::GUARD);

        ActivityLogger::log('login', description: 'Uji login portal.');

        $log = ActivityLog::where('action', 'login')->firstOrFail();

        // user_id adalah foreign key ke tabel staf — id akun portal di sana
        // bukan cuma salah baca, tapi melanggar constraint.
        $this->assertNull($log->user_id);
        $this->assertSame($akun->id, $log->customer_user_id);
        $this->assertStringContainsString('portal', $log->actor_name);
    }

    public function test_aksi_staf_tetap_dicatat_di_kolom_user(): void
    {
        $staf = $this->staffAdmin();
        $this->actingAs($staf);

        ActivityLogger::log('login', description: 'Uji login staf.');

        $log = ActivityLog::where('action', 'login')->firstOrFail();

        $this->assertSame($staf->id, $log->user_id);
        $this->assertNull($log->customer_user_id);
        $this->assertSame($staf->name, $log->actor_name);
    }
}
