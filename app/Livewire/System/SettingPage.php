<?php

namespace App\Livewire\System;

use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class SettingPage extends Component
{
    use WithFileUploads;

    /** Nilai seluruh setting, dikunci berdasarkan key. */
    public array $values = [];

    public $logo = null;

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
    }

    protected function rules(): array
    {
        return [
            'values.app_name' => ['required', 'string', 'max:100'],
            'values.company_name' => ['required', 'string', 'max:255'],
            'values.company_email' => ['nullable', 'email:filter', 'max:255'],

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

            'values.iot_push_interval_seconds' => ['required', 'integer', 'min:1'],
            'values.iot_offline_after_minutes' => ['required', 'integer', 'min:1'],
            'values.iot_retention_months' => ['required', 'integer', 'min:1'],
            // Boleh dikosongkan untuk mematikan autentikasi API; peringatannya
            // ditampilkan di halaman Setting.
            'values.api_token' => ['nullable', 'string', 'min:24', 'max:128'],

            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'values.app_name' => 'nama aplikasi',
            'values.company_name' => 'nama perusahaan',
            'values.billing_cut_off_day' => 'tanggal generate invoice',
            'values.billing_generate_time' => 'jam generate',
            'values.invoice_due_days' => 'jatuh tempo',
            'values.invoice_number_format' => 'format nomor invoice',
            'values.invoice_number_padding' => 'digit nomor urut',
            'values.biaya_admin' => 'biaya admin',
            'values.ppj_percent' => 'PPJ',
            'values.ppn_percent' => 'PPN',
            'values.invoice_rounding_to' => 'pembulatan total',
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
        ];
    }

    public function save(SettingService $settings): void
    {
        $this->authorize('setting.manage');

        $this->validate();

        if ($this->logo) {
            // Disimpan sebagai path relatif pada disk 'public'; view
            // merendernya lewat Storage::url().
            $this->values['company_logo'] = $this->logo->store('logo', 'public');
        }

        foreach ($this->values as $key => $value) {
            $settings->put($key, $value);
        }

        ActivityLogger::log('update_setting', description: 'Ubah setting sistem');

        $this->loadValues();
        $this->dispatch('toast', type: 'success', message: 'Setting tersimpan.');
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
