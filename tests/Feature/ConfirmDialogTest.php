<?php

namespace Tests\Feature;

use App\Livewire\Master\PowerMeterPage;
use App\Livewire\System\SettingPage;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * window.confirm() bawaan browser tampilannya generik dan tidak mengikuti
 * identitas visual aplikasi. Seluruh wire:confirm di aplikasi ini diganti
 * ConfirmDialog (public/assets/js/core/confirm.js) — dialog kustom yang
 * dipicu lewat Alpine (x-on:click) lalu memanggil method Livewire-nya sendiri
 * di dalam onConfirm, karena wire:confirm tidak bisa digabung dengan dialog
 * asinkron.
 */
class ConfirmDialogTest extends TestCase
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

    /**
     * Regresi menyeluruh: tidak boleh ada satu pun wire:confirm yang lolos
     * kembali ke aplikasi. Mengecek seluruh berkas, bukan cuma yang diketahui
     * hari ini — supaya penambahan aksi baru di masa depan tidak diam-diam
     * memakai dialog bawaan browser lagi.
     */
    public function test_tidak_ada_wireconfirm_tersisa_di_seluruh_view(): void
    {
        $offenders = [];

        foreach ((new Finder())->in(resource_path('views'))->files()->name('*.blade.php') as $file) {
            if (str_contains($file->getContents(), 'wire:confirm')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertEmpty($offenders, 'Masih memakai wire:confirm: '.implode(', ', $offenders));
    }

    public function test_hapus_power_meter_memakai_dialog_kustom_bergaya_bahaya(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-01', 'name' => 'Panel Satu', 'multiplier' => 1, 'status' => 'active',
        ]);

        Livewire::test(PowerMeterPage::class)
            ->assertDontSeeHtml('wire:confirm')
            ->assertSeeHtml('ConfirmDialog.show')
            // Aksi merusak (hapus) harus menyalakan tombol merah, bukan biru default.
            ->assertSeeHtml('danger: true')
            ->assertSeeHtml('$wire.delete('.$meter->id.')');
    }

    public function test_generate_token_memakai_dialog_kustom(): void
    {
        Livewire::test(SettingPage::class)
            ->assertDontSeeHtml('wire:confirm')
            ->assertSeeHtml('ConfirmDialog.show')
            ->assertSeeHtml('$wire.regenerateToken()');
    }
}
