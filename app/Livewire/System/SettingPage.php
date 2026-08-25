<?php

namespace App\Livewire\System;

use App\Mail\SmtpTestMail;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class SettingPage extends Component
{
    use WithFileUploads;

    /** Nilai seluruh setting, dikunci berdasarkan key. */
    public array $values = [];

    public $logo = null;

    /**
     * Apakah password SMTP sudah tersimpan.
     *
     * Nilainya sendiri tidak pernah dikirim ke browser — field passwordnya
     * selalu dimuat kosong. Halaman Setting bisa dibuka siapa pun yang punya
     * izin setting.manage, dan password mail server tidak perlu ikut terkirim
     * ke sana hanya untuk memberi tahu bahwa ia ada.
     */
    public bool $mailPasswordStored = false;

    public function mount(): void
    {
        $this->loadValues();
    }

    private function loadValues(): void
    {
        $this->values = Setting::pluck('value', 'key')->all();

        // Nilai boolean disimpan sebagai '0'/'1'; checkbox Livewire perlu bool
        // asli, kalau tidak '0' terbaca truthy dan centangnya selalu menyala.
        foreach (Setting::where('type', 'boolean')->pluck('key') as $key) {
            $this->values[$key] = filter_var($this->values[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // Nilai di tabel masih terenkripsi; yang penting di sini hanya ada
        // atau tidak, bukan isinya.
        $this->mailPasswordStored = filled($this->values['mail_password'] ?? null);
        $this->values['mail_password'] = '';
    }

    protected function rules(): array
    {
        return [
            'values.app_name' => ['required', 'string', 'max:100'],
            'values.company_name' => ['required', 'string', 'max:255'],
            'values.company_email' => ['nullable', 'email:filter', 'max:255'],
            /*
             * Empat identitas di bawah ini tercetak di kop invoice dan
             * kuitansi. Sebelumnya tidak punya aturan sama sekali — tersimpan
             * apa adanya, berapa pun panjangnya, dan kop dokumen yang
             * dikirim ke pelanggan ikut melebar tanpa ada yang menahan.
             */
            'values.company_address' => ['nullable', 'string', 'max:500'],
            'values.company_phone' => ['nullable', 'string', 'max:50'],
            'values.company_npwp' => ['nullable', 'string', 'max:50'],
            'values.company_domain' => ['nullable', 'string', 'max:255'],

            'values.billing_cut_off_day' => ['required', 'integer', 'between:1,28'],
            'values.billing_generate_time' => ['required', 'date_format:H:i'],
            'values.invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'values.invoice_number_format' => ['required', 'string', 'max:100'],
            'values.invoice_number_padding' => ['required', 'integer', 'between:1,10'],
            'values.biaya_admin' => ['required', 'numeric', 'min:0'],
            'values.ppj_percent' => ['required', 'numeric', 'between:0,100'],
            'values.ppn_percent' => ['required', 'numeric', 'between:0,100'],
            'values.invoice_rounding_to' => ['required', 'integer', 'min:0'],
            'values.invoice_auto_issue' => ['boolean'],
            'values.invoice_auto_send' => ['boolean'],
            'values.receipt_number_format' => ['required', 'string', 'max:100'],
            'values.receipt_auto_issue' => ['boolean'],
            'values.receipt_auto_send' => ['boolean'],
            'values.receipt_auto_send_days' => ['required', 'integer', 'between:0,30'],

            'values.mail_mailer' => ['nullable', 'in:smtp,log'],
            'values.mail_host' => ['nullable', 'string', 'max:255'],
            'values.mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'values.mail_username' => ['nullable', 'string', 'max:255'],
            'values.mail_password' => ['nullable', 'string', 'max:255'],
            'values.mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            // 'email:filter' menolak CRLF — alamat pengirim masuk ke header
            // email, tempat baris baru bisa dipakai menyuntik header lain.
            'values.mail_from_address' => ['nullable', 'email:filter', 'max:255'],
            'values.mail_from_name' => ['nullable', 'string', 'max:100'],

            'values.iot_push_interval_seconds' => ['required', 'integer', 'min:1'],
            'values.iot_offline_after_minutes' => ['required', 'integer', 'min:1'],
            'values.iot_retention_months' => ['required', 'integer', 'min:1'],
            // Boleh dikosongkan untuk mematikan autentikasi API; peringatannya
            // ditampilkan di halaman Setting. Token bisa ditulis manual (tidak
            // lagi hanya lewat generate), jadi spasi ditolak — perbandingannya
            // di AuthenticateGateway persis karakter demi karakter, spasi yang
            // tidak sengaja ikut ter-copy akan membuat gateway ditolak tanpa
            // sebab yang jelas.
            'values.api_token' => ['nullable', 'string', 'min:24', 'max:128', 'regex:/^\S+$/'],

            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'values.app_name' => 'nama aplikasi',
            'values.company_name' => 'nama perusahaan',
            'values.company_address' => 'alamat perusahaan',
            'values.company_phone' => 'telepon perusahaan',
            'values.company_npwp' => 'NPWP perusahaan',
            'values.company_domain' => 'domain perusahaan',
            'values.billing_cut_off_day' => 'tanggal generate invoice',
            'values.billing_generate_time' => 'jam generate',
            'values.invoice_due_days' => 'jatuh tempo',
            'values.invoice_number_format' => 'format nomor invoice',
            'values.invoice_number_padding' => 'digit nomor urut',
            'values.biaya_admin' => 'biaya admin',
            'values.ppj_percent' => 'PPJ',
            'values.ppn_percent' => 'PPN',
            'values.invoice_rounding_to' => 'pembulatan total',
            'values.receipt_number_format' => 'format nomor kuitansi',
            'values.receipt_auto_send_days' => 'jeda kirim kuitansi',
            'values.mail_host' => 'SMTP host',
            'values.mail_port' => 'SMTP port',
            'values.mail_username' => 'SMTP username',
            'values.mail_password' => 'SMTP password',
            'values.mail_from_address' => 'email pengirim',
            'values.mail_from_name' => 'nama pengirim',
            'values.api_token' => 'API token',
        ];
    }

    /**
     * Membuat token baru. Gateway harus diperbarui setelah ini, atau
     * kiriman datanya akan ditolak.
     */
    public function regenerateToken(): void
    {
        $this->authorize('setting.manage');

        $this->values['api_token'] = Str::random(48);

        $this->dispatch('toast', type: 'warning',
            message: 'Token baru dibuat. Klik Simpan lalu perbarui konfigurasi gateway.');
    }

    protected function messages(): array
    {
        return [
            // 29–31 tidak ada di setiap bulan, jadi tanggal generate dibatasi.
            'values.billing_cut_off_day.between' => 'Tanggal generate harus antara 1 sampai 28.',
            'values.api_token.regex' => 'API token tidak boleh mengandung spasi.',
        ];
    }

    public function save(SettingService $settings): void
    {
        $this->authorize('setting.manage');

        // Ditulis manual, jadi rawan ikut ter-copy spasi di ujungnya —
        // dibersihkan sebelum divalidasi supaya tidak lolos sebagai token
        // yang terlihat benar tapi gagal cocok persis di AuthenticateGateway.
        if (isset($this->values['api_token'])) {
            $this->values['api_token'] = trim($this->values['api_token']);
        }

        $this->validate();

        if ($this->logo) {
            // Disimpan sebagai path relatif pada disk 'public'; view
            // merendernya lewat Storage::url().
            $this->values['company_logo'] = $this->logo->store('logo', 'public');
        }

        $values = $this->values;

        /*
         * Field password yang dibiarkan kosong berarti "jangan diubah", bukan
         * "hapus". Tanpa penjagaan ini, setiap penyimpanan setelan apa pun —
         * mengganti PPN, misalnya — akan menghapus password SMTP, dan
         * pengiriman email berhenti tanpa ada yang menyentuh setelan mail.
         * Untuk benar-benar menghapusnya ada tombol tersendiri.
         */
        if (($values['mail_password'] ?? '') === '') {
            unset($values['mail_password']);
        }

        foreach ($values as $key => $value) {
            $settings->put($key, $value);
        }

        ActivityLogger::log('update_setting', description: 'Ubah setting sistem');

        $this->loadValues();
        $this->dispatch('toast', type: 'success', message: 'Setting tersimpan.');
    }

    /**
     * Menghapus password SMTP yang tersimpan.
     *
     * Dibutuhkan relay internal yang justru menolak autentikasi: field kosong
     * saja tidak cukup karena kosong berarti "jangan diubah".
     */
    public function clearMailPassword(SettingService $settings): void
    {
        $this->authorize('setting.manage');

        $settings->put('mail_password', '');
        $this->values['mail_password'] = '';
        $this->mailPasswordStored = false;

        ActivityLogger::log('update_setting', description: 'Hapus password SMTP');
        $this->dispatch('toast', type: 'warning', message: 'Password SMTP dihapus.');
    }

    /**
     * Mengirim email percobaan ke alamat operator yang sedang masuk.
     *
     * Dikirim langsung, tidak lewat antrean: seluruh gunanya justru
     * memperlihatkan kegagalan SMTP di layar sekarang. Email aplikasi yang
     * sebenarnya berjalan lewat antrean, dan di sana kegagalan hanya masuk log
     * worker — invoice berhenti terkirim tanpa gejala di aplikasi.
     *
     * Memakai konfigurasi yang SUDAH TERSIMPAN, karena config mail diterapkan
     * saat boot. Perubahan yang belum disimpan tidak ikut teruji.
     */
    public function sendTestEmail(): void
    {
        $this->authorize('setting.manage');

        $to = auth()->user()?->email;

        if (!$to) {
            $this->dispatch('toast', type: 'error',
                message: 'Akun Anda belum punya alamat email, jadi tidak ada tujuan uji.');

            return;
        }

        try {
            Mail::to($to)->send(new SmtpTestMail(setting('app_name', 'Energy Billing')));
        } catch (\Throwable $e) {
            report($e);

            $this->dispatch('toast', type: 'error', message: 'Gagal mengirim: '.$e->getMessage());

            return;
        }

        ActivityLogger::log('update_setting', description: "Kirim email uji SMTP ke {$to}");
        $this->dispatch('toast', type: 'success', message: "Email uji terkirim ke {$to}.");
    }

    public function render()
    {
        return view('livewire.system.setting-page', [
            'groups' => Setting::orderBy('id')->get()->groupBy('group'),
            'ingestUrl' => url('/api/v1/readings'),
            'docsUrl' => url('/api/documentation'),
        ]);
    }
}
