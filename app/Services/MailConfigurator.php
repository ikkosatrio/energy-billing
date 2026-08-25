<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;

/**
 * Menerapkan setelan SMTP dari halaman Setting ke config mail saat boot.
 *
 * Kenapa perlu: config('mail.*') dibaca dari .env, dan .env hanya bisa diubah
 * dengan akses shell ke server. Operator yang perlu mengganti mail server —
 * atau memperbaiki password yang kedaluwarsa — tidak punya akses itu, dan
 * gejalanya bukan error yang terlihat: invoice dan kuitansi berhenti terkirim
 * tanpa ada yang tahu, karena pengirimannya lewat antrean.
 *
 * PRESEDENSI: .env tetap jadi dasar. Setelan database hanya mengambil alih
 * kunci yang benar-benar diisi, dan seluruh blok SMTP hanya diambil alih bila
 * Host terisi — setengah konfigurasi dari database dan setengah dari .env
 * menghasilkan sambungan yang gagal dengan pesan yang menyesatkan.
 */
class MailConfigurator
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function apply(Repository $config): void
    {
        $mailer = $this->value('mail_mailer');

        if ($mailer) {
            $config->set('mail.default', $mailer);
        }

        // Alamat pengirim berdiri sendiri: berlaku untuk mailer apa pun,
        // termasuk 'log' saat pengujian.
        foreach (['mail_from_address' => 'mail.from.address', 'mail_from_name' => 'mail.from.name'] as $key => $path) {
            if ($value = $this->value($key)) {
                $config->set($path, $value);
            }
        }

        if (!$host = $this->value('mail_host')) {
            return;
        }

        $config->set('mail.mailers.smtp.host', $host);

        if ($port = $this->value('mail_port')) {
            $config->set('mail.mailers.smtp.port', (int) $port);
        }

        /*
         * Username dan password ditulis apa adanya, termasuk saat kosong.
         *
         * Sebagian relay internal memang tanpa autentikasi, dan membiarkan
         * kredensial .env lama menempel pada host baru membuat sambungannya
         * ditolak — dengan pesan yang menunjuk ke password, bukan ke setelan
         * yang sebenarnya salah.
         */
        $config->set('mail.mailers.smtp.username', $this->value('mail_username'));
        $config->set('mail.mailers.smtp.password', $this->value('mail_password'));

        $encryption = $this->value('mail_encryption');

        if ($encryption) {
            // 'none' adalah pilihan eksplisit di UI untuk mematikan TLS, dan
            // config mail menunggu null untuk itu, bukan string 'none'.
            $config->set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);
        }
    }

    /** Setelan kosong dianggap tidak diisi, sehingga .env tetap berlaku. */
    private function value(string $key): ?string
    {
        $value = $this->settings->get($key);
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
