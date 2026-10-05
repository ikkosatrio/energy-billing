<?php

namespace Tests\Feature;

use App\Livewire\System\SettingPage;
use App\Mail\SmtpTestMail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailConfigurator;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Setelan SMTP dari halaman Setting.
 *
 * Dua hal yang dijaga di sini: password tidak pernah tersimpan atau terkirim
 * sebagai teks biasa, dan .env tetap berlaku sampai setelannya benar-benar
 * diisi — supaya menambah kartu ini tidak mematikan email di instalasi yang
 * sudah jalan.
 */
class MailSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->actingAs($this->admin());
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    private function apply(): void
    {
        app(SettingService::class)->forget();
        app(MailConfigurator::class)->apply(config());
    }

    // ── Presedensi terhadap .env ─────────────────────────────────────────

    public function test_setelan_kosong_membiarkan_config_env_apa_adanya(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'dari.env',
            'mail.mailers.smtp.password' => 'rahasia-env',
        ]);

        $this->apply();

        $this->assertSame('dari.env', config('mail.mailers.smtp.host'));
        $this->assertSame('rahasia-env', config('mail.mailers.smtp.password'));
    }

    public function test_host_terisi_mengambil_alih_seluruh_blok_smtp(): void
    {
        $settings = app(SettingService::class);
        $settings->put('mail_host', 'smtp.perusahaan.test');
        $settings->put('mail_port', '465');
        $settings->put('mail_username', 'billing@perusahaan.test');
        $settings->put('mail_password', 'rahasia-db');
        $settings->put('mail_encryption', 'ssl');

        $this->apply();

        $this->assertSame('smtp.perusahaan.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('billing@perusahaan.test', config('mail.mailers.smtp.username'));
        $this->assertSame('rahasia-db', config('mail.mailers.smtp.password'));
        $this->assertSame('ssl', config('mail.mailers.smtp.encryption'));
    }

    /** 'none' adalah pilihan eksplisit; config mail menunggu null, bukan string. */
    public function test_enkripsi_none_menjadi_null(): void
    {
        app(SettingService::class)->put('mail_host', 'relay.internal.test');
        app(SettingService::class)->put('mail_encryption', 'none');

        $this->apply();

        $this->assertNull(config('mail.mailers.smtp.encryption'));
    }

    /**
     * Relay tanpa autentikasi: kredensial .env lama tidak boleh menempel pada
     * host baru, karena penolakannya akan menunjuk ke password.
     */
    public function test_host_baru_tanpa_kredensial_membersihkan_kredensial_env(): void
    {
        config(['mail.mailers.smtp.username' => 'lama', 'mail.mailers.smtp.password' => 'lama']);

        app(SettingService::class)->put('mail_host', 'relay.internal.test');
        $this->apply();

        $this->assertNull(config('mail.mailers.smtp.username'));
        $this->assertNull(config('mail.mailers.smtp.password'));
    }

    public function test_alamat_pengirim_berlaku_tanpa_perlu_host(): void
    {
        app(SettingService::class)->put('mail_from_address', 'tagihan@perusahaan.test');
        app(SettingService::class)->put('mail_from_name', 'Billing Perusahaan');

        $this->apply();

        $this->assertSame('tagihan@perusahaan.test', config('mail.from.address'));
        $this->assertSame('Billing Perusahaan', config('mail.from.name'));
    }

    // ── Password ─────────────────────────────────────────────────────────

    public function test_password_tersimpan_terenkripsi_bukan_teks_biasa(): void
    {
        Livewire::test(SettingPage::class)
            ->set('values.mail_host', 'smtp.perusahaan.test')
            ->set('values.mail_password', 'rahasia-sekali')
            ->call('save')
            ->assertHasNoErrors();

        $tersimpan = Setting::where('key', 'mail_password')->value('value');

        $this->assertNotSame('rahasia-sekali', $tersimpan);
        $this->assertStringNotContainsString('rahasia-sekali', (string) $tersimpan);
        $this->assertSame('rahasia-sekali', Crypt::decryptString($tersimpan));
        // Dan terbaca kembali apa adanya lewat helper setting().
        app(SettingService::class)->forget();
        $this->assertSame('rahasia-sekali', setting('mail_password'));
    }

    public function test_password_tidak_pernah_dikirim_ke_browser(): void
    {
        app(SettingService::class)->put('mail_password', 'rahasia-sekali');

        Livewire::test(SettingPage::class)
            ->assertSet('values.mail_password', '')
            ->assertSet('mailPasswordStored', true)
            ->assertDontSee('rahasia-sekali');
    }

    /**
     * Menyimpan setelan lain tidak boleh menghapus password. Tanpa penjagaan
     * ini, mengganti PPN saja sudah mematikan pengiriman email.
     */
    public function test_menyimpan_setelan_lain_tidak_menghapus_password(): void
    {
        app(SettingService::class)->put('mail_password', 'rahasia-sekali');

        Livewire::test(SettingPage::class)
            ->set('values.ppn_percent', '12')
            ->call('save')
            ->assertHasNoErrors();

        app(SettingService::class)->forget();
        $this->assertSame('rahasia-sekali', setting('mail_password'));
    }

    public function test_tombol_hapus_password_mengosongkannya(): void
    {
        app(SettingService::class)->put('mail_password', 'rahasia-sekali');

        Livewire::test(SettingPage::class)
            ->call('clearMailPassword')
            ->assertSet('mailPasswordStored', false);

        app(SettingService::class)->forget();
        $this->assertNull(setting('mail_password'));
    }

    /** APP_KEY berganti: setelan lain harus tetap terbaca, bukan ikut mati. */
    public function test_password_yang_tidak_bisa_didekripsi_dianggap_kosong(): void
    {
        Setting::where('key', 'mail_password')->update(['value' => 'bukan-ciphertext-yang-sah']);
        app(SettingService::class)->forget();

        $this->assertNull(setting('mail_password'));
        $this->assertSame('Energy Billing', setting('app_name'));
    }

    // ── Email uji ────────────────────────────────────────────────────────

    /**
     * Tujuannya alamat pengirim yang sedang disetel, bukan alamat operator:
     * satu kiriman menguji kredensial SMTP sekaligus memastikan alamat
     * pengirimnya memang kotak yang bisa diperiksa.
     */
    public function test_email_uji_dikirim_ke_alamat_pengirim_yang_disetel(): void
    {
        Mail::fake();
        app(SettingService::class)->put('mail_from_address', 'billing@perusahaan.test');

        Livewire::test(SettingPage::class)
            ->call('sendTestEmail')
            ->assertDispatched('toast', type: 'success');

        Mail::assertSent(SmtpTestMail::class, fn ($mail) => $mail->hasTo('billing@perusahaan.test'));
    }

    /** Email Pengirim kosong berarti setelan mail masih ikut .env. */
    public function test_email_uji_jatuh_ke_alamat_operator_bila_pengirim_kosong(): void
    {
        Mail::fake();

        Livewire::test(SettingPage::class)
            ->call('sendTestEmail')
            ->assertDispatched('toast', type: 'success');

        Mail::assertSent(SmtpTestMail::class, fn ($mail) => $mail->hasTo('admin@test.local'));
    }

    public function test_email_uji_melaporkan_kegagalan_smtp(): void
    {
        Mail::shouldReceive('to->send')->once()->andThrow(new \RuntimeException('Connection could not be established'));

        Livewire::test(SettingPage::class)
            ->call('sendTestEmail')
            ->assertDispatched('toast', type: 'error');
    }

    public function test_nilai_smtp_tidak_valid_ditolak(): void
    {
        Livewire::test(SettingPage::class)
            ->set('values.mail_port', '70000')
            ->set('values.mail_from_address', 'bukan-email')
            ->set('values.mail_encryption', 'entah')
            ->call('save')
            ->assertHasErrors([
                'values.mail_port',
                'values.mail_from_address',
                'values.mail_encryption',
            ]);
    }
}
