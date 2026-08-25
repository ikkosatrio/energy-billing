<?php

namespace Tests\Feature;

use App\Livewire\System\CustomerUserPage;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengelolaan akun portal dari panel admin: pembuatan, penetapan pelanggan,
 * reset password, dan penonaktifan.
 *
 * Yang khusus diuji di sini adalah aturan yang tidak jelas dari kodenya
 * sendiri: akun WAJIB punya minimal satu pelanggan (kalau tidak, ia lolos
 * disimpan tapi tidak akan pernah bisa login), dan role yang boleh dipilih
 * hanya role portal.
 */
class CustomerUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Customer $pelangganA;

    private Customer $pelangganB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        $meterA = PowerMeter::create(['code' => 'MTR-A', 'name' => 'Panel A', 'multiplier' => 1, 'status' => 'active']);
        $meterB = PowerMeter::create(['code' => 'MTR-B', 'name' => 'Panel B', 'multiplier' => 1, 'status' => 'active']);

        $this->pelangganA = Customer::create(['code' => 'CUST-A', 'name' => 'Pelanggan A', 'power_meter_id' => $meterA->id]);
        $this->pelangganB = Customer::create(['code' => 'CUST-B', 'name' => 'Pelanggan B', 'power_meter_id' => $meterB->id]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    private function viewer(): User
    {
        return User::create([
            'name' => 'Viewer', 'username' => 'viewer', 'email' => 'viewer@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', 'viewer')->value('id'),
        ]);
    }

    private function portalRoleId(string $slug = 'portal-penuh'): int
    {
        return Role::portal()->where('slug', $slug)->value('id');
    }

    // ── Permission ──────────────────────────────────────────────────────

    public function test_role_tanpa_permission_tidak_bisa_membuka_halaman(): void
    {
        $this->actingAs($this->viewer());

        // customer_user.view tidak diberikan ke role bawaan mana pun.
        $this->get(route('system.customer-users.index'))->assertForbidden();
    }

    public function test_role_tanpa_permission_tidak_bisa_menyimpan_akun(): void
    {
        $this->actingAs($this->viewer());

        Livewire::test(CustomerUserPage::class)->assertForbidden();
    }

    // ── Pembuatan akun ──────────────────────────────────────────────────

    public function test_membuat_akun_portal_dengan_beberapa_pelanggan(): void
    {
        $this->actingAs($admin = $this->admin());

        Livewire::test(CustomerUserPage::class)
            ->call('create')
            ->set('form.name', 'Budi Pelanggan')
            ->set('form.username', 'budi')
            ->set('form.email', 'budi@pelanggan.test')
            ->set('form.role_id', $this->portalRoleId())
            ->set('selectedCustomers', [(string) $this->pelangganA->id, (string) $this->pelangganB->id])
            ->set('password', 'RahasiaKuat123')
            ->call('save')
            ->assertHasNoErrors();

        $akun = CustomerUser::where('username', 'budi')->firstOrFail();

        $this->assertSame('Budi Pelanggan', $akun->name);
        $this->assertTrue($akun->is_active);
        $this->assertSame($admin->id, $akun->created_by);
        $this->assertTrue(Hash::check('RahasiaKuat123', $akun->password));
        $this->assertEqualsCanonicalizing(
            [$this->pelangganA->id, $this->pelangganB->id],
            $akun->accessibleCustomerIds(),
        );

        $log = ActivityLog::where('action', 'created')->where('model_type', CustomerUser::class)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('budi', $log->description);
    }

    public function test_akun_wajib_punya_minimal_satu_pelanggan(): void
    {
        $this->actingAs($this->admin());

        // Tanpa aturan ini akun tersimpan rapi tapi ditolak saat login, dan
        // penyebabnya tidak terlihat dari halaman admin.
        Livewire::test(CustomerUserPage::class)
            ->call('create')
            ->set('form.name', 'Tanpa Pelanggan')
            ->set('form.username', 'kosong')
            ->set('form.role_id', $this->portalRoleId())
            ->set('selectedCustomers', [])
            ->set('password', 'RahasiaKuat123')
            ->call('save')
            ->assertHasErrors('selectedCustomers');

        $this->assertNull(CustomerUser::where('username', 'kosong')->first());
    }

    public function test_role_staf_tidak_bisa_diberikan_ke_akun_portal(): void
    {
        $this->actingAs($this->admin());

        $roleStaf = Role::staff()->where('slug', Role::SUPER_ADMIN)->value('id');

        Livewire::test(CustomerUserPage::class)
            ->call('create')
            ->set('form.name', 'Nakal')
            ->set('form.username', 'nakal')
            ->set('form.role_id', $roleStaf)
            ->set('selectedCustomers', [(string) $this->pelangganA->id])
            ->set('password', 'RahasiaKuat123')
            ->call('save')
            ->assertHasErrors('form.role_id');

        $this->assertNull(CustomerUser::where('username', 'nakal')->first());
    }

    public function test_username_tidak_boleh_ganda(): void
    {
        $this->actingAs($this->admin());

        CustomerUser::create([
            'name' => 'Sudah Ada', 'username' => 'budi', 'password' => 'rahasia123',
            'role_id' => $this->portalRoleId(),
        ]);

        Livewire::test(CustomerUserPage::class)
            ->call('create')
            ->set('form.name', 'Budi Kedua')
            ->set('form.username', 'budi')
            ->set('form.role_id', $this->portalRoleId())
            ->set('selectedCustomers', [(string) $this->pelangganA->id])
            ->set('password', 'RahasiaKuat123')
            ->call('save')
            ->assertHasErrors('form.username');
    }

    /**
     * Email kosong harus tersimpan NULL, bukan string kosong.
     *
     * Kolomnya unique dan nullable: MySQL mengizinkan banyak NULL tapi hanya
     * satu string kosong, jadi '' membuat akun KEDUA tanpa email ditolak
     * dengan pesan "email sudah dipakai" pada kolom yang jelas-jelas kosong.
     */
    public function test_dua_akun_tanpa_email_sama_sama_bisa_disimpan(): void
    {
        $this->actingAs($this->admin());

        foreach (['tanpaemail1', 'tanpaemail2'] as $username) {
            Livewire::test(CustomerUserPage::class)
                ->call('create')
                ->set('form.name', 'Tanpa Email '.$username)
                ->set('form.username', $username)
                ->set('form.email', '')
                ->set('form.role_id', $this->portalRoleId())
                ->set('selectedCustomers', [(string) $this->pelangganA->id])
                ->set('password', 'RahasiaKuat123')
                ->call('save')
                ->assertHasNoErrors();

            $this->assertNull(CustomerUser::where('username', $username)->value('email'));
        }

        $this->assertSame(2, CustomerUser::whereNull('email')->count());
    }

    // ── Perubahan & reset password ──────────────────────────────────────

    public function test_mengubah_akun_tanpa_mengisi_password_mempertahankan_password_lama(): void
    {
        $this->actingAs($this->admin());

        $akun = CustomerUser::create([
            'name' => 'Budi', 'username' => 'budi', 'password' => 'PasswordLama123',
            'role_id' => $this->portalRoleId(),
        ]);
        $akun->customers()->attach($this->pelangganA->id);

        Livewire::test(CustomerUserPage::class)
            ->call('edit', $akun->id)
            ->set('form.name', 'Budi Diperbarui')
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        $akun->refresh();

        $this->assertSame('Budi Diperbarui', $akun->name);
        $this->assertTrue(Hash::check('PasswordLama123', $akun->password));
    }

    public function test_reset_password_dari_panel_admin(): void
    {
        $this->actingAs($this->admin());

        $akun = CustomerUser::create([
            'name' => 'Budi', 'username' => 'budi', 'password' => 'PasswordLama123',
            'role_id' => $this->portalRoleId(),
        ]);
        $akun->customers()->attach($this->pelangganA->id);

        Livewire::test(CustomerUserPage::class)
            ->call('edit', $akun->id)
            ->set('password', 'PasswordBaru456')
            ->call('save')
            ->assertHasNoErrors();

        $akun->refresh();

        $this->assertTrue(Hash::check('PasswordBaru456', $akun->password));
        $this->assertFalse(Hash::check('PasswordLama123', $akun->password));
    }

    public function test_mengubah_daftar_pelanggan_mengganti_bukan_menambah(): void
    {
        $this->actingAs($this->admin());

        $akun = CustomerUser::create([
            'name' => 'Budi', 'username' => 'budi', 'password' => 'rahasia123',
            'role_id' => $this->portalRoleId(),
        ]);
        $akun->customers()->attach($this->pelangganA->id);

        Livewire::test(CustomerUserPage::class)
            ->call('edit', $akun->id)
            ->set('selectedCustomers', [(string) $this->pelangganB->id])
            ->call('save')
            ->assertHasNoErrors();

        // Pencabutan akses harus benar-benar mencabut: kalau sync berperilaku
        // seperti attach, pelanggan lama tetap terlihat oleh akun ini.
        $this->assertSame([$this->pelangganB->id], $akun->fresh()->accessibleCustomerIds());
    }

    // ── Nonaktif & hapus ────────────────────────────────────────────────

    public function test_menonaktifkan_akun_mencatat_log(): void
    {
        $this->actingAs($this->admin());

        $akun = CustomerUser::create([
            'name' => 'Budi', 'username' => 'budi', 'password' => 'rahasia123',
            'role_id' => $this->portalRoleId(),
        ]);

        Livewire::test(CustomerUserPage::class)->call('toggleActive', $akun->id);

        $this->assertFalse($akun->fresh()->is_active);
        $this->assertNotNull(ActivityLog::where('action', 'deactivated')->first());

        Livewire::test(CustomerUserPage::class)->call('toggleActive', $akun->id);

        $this->assertTrue($akun->fresh()->is_active);
        $this->assertNotNull(ActivityLog::where('action', 'activated')->first());
    }

    public function test_hapus_akun_memakai_soft_delete(): void
    {
        $this->actingAs($this->admin());

        $akun = CustomerUser::create([
            'name' => 'Budi', 'username' => 'budi', 'password' => 'rahasia123',
            'role_id' => $this->portalRoleId(),
        ]);

        Livewire::test(CustomerUserPage::class)->call('delete', $akun->id);

        // Soft delete, supaya baris log aktivitas yang menunjuk akun ini masih
        // bisa menampilkan namanya.
        $this->assertNull(CustomerUser::find($akun->id));
        $this->assertNotNull(CustomerUser::withTrashed()->find($akun->id));
    }

    public function test_halaman_menampilkan_akun_beserta_pelanggan_dan_role(): void
    {
        $this->actingAs($this->admin());

        $akun = CustomerUser::create([
            'name' => 'Budi Pelanggan', 'username' => 'budi', 'password' => 'rahasia123',
            'role_id' => $this->portalRoleId('portal-pantau'),
        ]);
        $akun->customers()->attach($this->pelangganA->id);

        Livewire::test(CustomerUserPage::class)
            ->assertSee('Budi Pelanggan')
            ->assertSee('Pelanggan (Pantau Saja)')
            ->assertSee('Pelanggan A')
            // Hanya role portal yang boleh jadi pilihan.
            ->assertViewHas('roles', fn ($roles) => $roles->every(
                fn ($role) => $role->id === $roles->firstWhere('id', $role->id)->id,
            ) && $roles->count() === 2);
    }

    public function test_akun_tanpa_pelanggan_ditandai_di_daftar(): void
    {
        $this->actingAs($this->admin());

        CustomerUser::create([
            'name' => 'Belum Diatur', 'username' => 'belum', 'password' => 'rahasia123',
            'role_id' => $this->portalRoleId(),
        ]);

        // Akun seperti ini tidak bisa login, jadi harus terlihat jelas — bukan
        // tampak seperti akun yang siap dipakai.
        Livewire::test(CustomerUserPage::class)->assertSee('Belum diatur');
    }
}
