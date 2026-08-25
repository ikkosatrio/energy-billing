<?php

namespace Tests\Feature;

use App\Livewire\System\SettingPage;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Submit halaman Setting.
 *
 * Halaman ini menyimpan seluruh setting dalam satu tombol, jadi satu key yang
 * tidak punya aturan validasi atau tidak ikut tersimpan tidak memunculkan
 * error apa pun — nilainya hanya diam-diam kembali ke yang lama, dan baru
 * ketahuan lewat perilaku aplikasi yang tidak berubah setelah disetel.
 */
class SettingSaveTest extends TestCase
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

    /** Nilai uji per key: berbeda dari default supaya perubahannya terbukti. */
    private const UBAHAN = [
        'app_name' => 'Billing Uji',
        'company_name' => 'PT Uji Coba Sejahtera',
        'company_email' => 'tagihan@uji.test',
        'billing_cut_off_day' => '7',
        'billing_generate_time' => '02:45',
        'invoice_due_days' => '21',
        'invoice_number_format' => 'TAG/{YYYY}/{SEQ}',
        'invoice_number_padding' => '5',
        'biaya_admin' => '17500',
        'ppj_percent' => '3.5',
        'ppn_percent' => '11',
        'invoice_rounding_to' => '500',
        'invoice_auto_issue' => true,
        'invoice_auto_send' => true,
        'receipt_number_format' => 'KWT/{YY}/{MM}/{SEQ}',
        'receipt_auto_issue' => true,
        'receipt_auto_send' => true,
        'receipt_auto_send_days' => '5',
        'iot_push_interval_seconds' => '300',
        'iot_offline_after_minutes' => '15',
        'iot_retention_months' => '36',
    ];

    public function test_submit_menyimpan_seluruh_nilai(): void
    {
        $component = Livewire::test(SettingPage::class);

        foreach (self::UBAHAN as $key => $value) {
            $component->set("values.{$key}", $value);
        }

        $component->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        app(SettingService::class)->forget();

        foreach (self::UBAHAN as $key => $expected) {
            $tersimpan = Setting::where('key', $key)->value('value');

            if (is_bool($expected)) {
                $this->assertSame('1', (string) $tersimpan, "setting {$key} tidak tersimpan");

                continue;
            }

            $this->assertSame($expected, (string) $tersimpan, "setting {$key} tidak tersimpan");
        }
    }

    /** Boolean yang dimatikan harus benar-benar tersimpan sebagai '0'. */
    public function test_boolean_yang_dimatikan_tersimpan_bukan_diabaikan(): void
    {
        foreach (['invoice_auto_issue', 'invoice_auto_send', 'receipt_auto_issue', 'receipt_auto_send'] as $key) {
            app(SettingService::class)->put($key, true);
        }

        Livewire::test(SettingPage::class)
            ->set('values.invoice_auto_issue', false)
            ->set('values.invoice_auto_send', false)
            ->set('values.receipt_auto_issue', false)
            ->set('values.receipt_auto_send', false)
            ->call('save')
            ->assertHasNoErrors();

        app(SettingService::class)->forget();

        foreach (['invoice_auto_issue', 'invoice_auto_send', 'receipt_auto_issue', 'receipt_auto_send'] as $key) {
            $this->assertSame('0', (string) Setting::where('key', $key)->value('value'), "setting {$key} tidak dimatikan");
            $this->assertFalse((bool) setting($key), "setting {$key} masih terbaca aktif");
        }
    }

    /**
     * Setiap key yang bisa diubah dari halaman ini harus punya aturan
     * validasi. Yang tidak punya akan tetap tersimpan tanpa diperiksa sama
     * sekali — termasuk nilai yang membuat penagihan salah hitung.
     */
    public function test_setiap_setelan_yang_disimpan_punya_aturan_validasi(): void
    {
        $component = Livewire::test(SettingPage::class);

        // rules() protected; dibaca lewat refleksi supaya daftarnya tidak
        // perlu ditulis ulang di test dan ikut basi saat aturannya berubah.
        // setAccessible() tidak dipakai: sejak PHP 8.1 method protected sudah
        // bisa langsung di-invoke lewat refleksi, dan pemanggilannya deprecated
        // di PHP 8.5.
        $rules = array_keys(
            (new \ReflectionMethod(SettingPage::class, 'rules'))->invoke($component->instance()),
        );

        $tanpaAturan = Setting::pluck('key')
            // Logo diunggah lewat properti tersendiri, bukan lewat values[].
            ->reject(fn ($key) => $key === 'company_logo')
            ->reject(fn ($key) => in_array("values.{$key}", $rules, true))
            ->values()
            ->all();

        $this->assertSame([], $tanpaAturan, 'setelan tanpa aturan validasi: '.implode(', ', $tanpaAturan));
    }

    public function test_nilai_tidak_valid_ditolak_dan_tidak_ada_yang_tersimpan(): void
    {
        Livewire::test(SettingPage::class)
            // 29 tidak ada di setiap bulan; dibatasi 1–28.
            ->set('values.billing_cut_off_day', '29')
            ->set('values.app_name', 'Tidak Boleh Tersimpan')
            ->call('save')
            ->assertHasErrors(['values.billing_cut_off_day']);

        app(SettingService::class)->forget();

        $this->assertNotSame('Tidak Boleh Tersimpan', (string) Setting::where('key', 'app_name')->value('value'));
    }

    public function test_tanpa_izin_setting_manage_submit_ditolak(): void
    {
        $this->actingAs(User::create([
            'name' => 'Operator', 'username' => 'operator', 'email' => 'op@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', '!=', Role::SUPER_ADMIN)->value('id'),
        ]));

        Livewire::test(SettingPage::class)
            ->set('values.app_name', 'Dicoba Operator')
            ->call('save')
            ->assertForbidden();
    }
}
