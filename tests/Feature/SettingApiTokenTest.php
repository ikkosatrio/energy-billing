<?php

namespace Tests\Feature;

use App\Livewire\System\SettingPage;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * API Token Gateway sekarang bisa ditulis manual, tidak lagi hanya lewat
 * tombol Generate. Perbandingannya di AuthenticateGateway memakai
 * hash_equals — persis karakter demi karakter — sehingga spasi yang tidak
 * sengaja ikut ter-copy harus ditolak atau dibersihkan, bukan lolos sebagai
 * token yang terlihat benar tapi selalu gagal dipakai gateway.
 */
class SettingApiTokenTest extends TestCase
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

    public function test_token_bisa_ditulis_manual(): void
    {
        $manual = 'gateway-utama-token-tulis-manual-123456';

        Livewire::test(SettingPage::class)
            ->set('values.api_token', $manual)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($manual, app(SettingService::class)->get('api_token'));
    }

    public function test_spasi_di_ujung_token_dibersihkan_otomatis(): void
    {
        $manual = 'gateway-utama-token-tulis-manual-123456';

        // Kesalahan yang paling umum: token ikut ter-copy dengan spasi atau
        // baris baru menempel di ujungnya.
        Livewire::test(SettingPage::class)
            ->set('values.api_token', "  {$manual}\n")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($manual, app(SettingService::class)->get('api_token'));
    }

    public function test_token_dengan_spasi_di_tengah_ditolak(): void
    {
        Livewire::test(SettingPage::class)
            ->set('values.api_token', 'token dengan spasi di tengah nilainya')
            ->call('save')
            ->assertHasErrors(['values.api_token' => 'regex']);
    }

    public function test_token_kosong_tetap_boleh_untuk_mematikan_autentikasi(): void
    {
        Livewire::test(SettingPage::class)
            ->set('values.api_token', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('', app(SettingService::class)->get('api_token'));
    }

    public function test_token_kurang_dari_24_karakter_ditolak(): void
    {
        Livewire::test(SettingPage::class)
            ->set('values.api_token', 'token-pendek')
            ->call('save')
            ->assertHasErrors(['values.api_token' => 'min']);
    }

    public function test_generate_token_tetap_menghasilkan_token_acak(): void
    {
        Livewire::test(SettingPage::class)
            ->call('regenerateToken')
            ->assertSet('values', fn ($values) => strlen($values['api_token']) === 48);
    }
}
