<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buku Panduan.
 *
 * Yang dijaga di sini bukan isi tulisannya, melainkan dua hal yang mudah
 * rusak diam-diam: dokumennya masih bisa dibuat, dan siapa pun yang sudah
 * masuk bisa membukanya — termasuk peran dengan izin paling sedikit, yang
 * justru paling membutuhkannya.
 */
class GuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    private function actingAsRole(string $slug): User
    {
        $user = User::create([
            'name' => 'Uji '.$slug,
            'username' => 'uji-'.$slug,
            'email' => $slug.'@test.local',
            'password' => 'secret123',
            'role_id' => Role::where('slug', $slug)->value('id'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_panduan_bisa_dibuka_dan_menghasilkan_pdf(): void
    {
        $this->actingAsRole(Role::SUPER_ADMIN);

        $response = $this->get(route('guide.show'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_panduan_bisa_diunduh(): void
    {
        $this->actingAsRole(Role::SUPER_ADMIN);

        $this->get(route('guide.download'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Inti dari keputusan menaruh menunya tanpa syarat izin: peran dengan hak
     * paling sedikit pun harus tetap bisa membaca panduannya.
     */
    public function test_peran_paling_terbatas_tetap_bisa_membuka_panduan(): void
    {
        $this->actingAsRole('viewer');

        $this->get(route('guide.show'))->assertOk();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('guide.show'))->assertRedirect(route('login'));
    }

    public function test_menu_panduan_terdaftar_dan_terbuka_untuk_semua(): void
    {
        $entry = collect(config('menu'))->firstWhere('route', 'guide.show');

        $this->assertNotNull($entry, 'Menu Buku Panduan hilang dari config/menu.php');
        $this->assertSame([], $entry['permits']);
        // Isinya berkas PDF, bukan halaman aplikasi — wire:navigate akan rusak
        // kalau penanda ini hilang.
        $this->assertTrue($entry['external'] ?? false);
    }
}
