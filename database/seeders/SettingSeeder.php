<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Nilai awal setting sistem. Aman dijalankan ulang: hanya membuat baris yang
 * belum ada, jadi perubahan yang sudah dibuat lewat halaman Setting tidak
 * ikut tertimpa.
 */
class SettingSeeder extends Seeder
{
    /**
     * [key, value, type, group, label]
     */
    private const SETTINGS = [
        // --- Identitas aplikasi ---
        ['app_name', 'Energy Billing', 'string', 'identity', 'Nama Aplikasi'],
        ['company_name', 'PT Contoh Gudang Nusantara', 'string', 'identity', 'Nama Perusahaan'],
        ['company_address', '', 'string', 'identity', 'Alamat'],
        ['company_phone', '', 'string', 'identity', 'Telepon'],
        ['company_email', '', 'string', 'identity', 'Email'],
        ['company_npwp', '', 'string', 'identity', 'NPWP'],
        ['company_domain', '', 'string', 'identity', 'Domain'],
        ['company_logo', '', 'string', 'identity', 'Logo'],

        // --- Billing & invoice ---
        // Tanggal default generate invoice; bisa di-override per pelanggan
        // lewat kolom customers.billing_day.
        ['billing_cut_off_day', '1', 'number', 'billing', 'Tanggal Generate Invoice'],
        ['billing_generate_time', '00:15', 'string', 'billing', 'Jam Generate'],
        ['invoice_due_days', '14', 'number', 'billing', 'Jatuh Tempo (hari)'],
        // {YYYY} tahun, {MM} bulan, {SEQ} nomor urut dalam periode.
        ['invoice_number_format', 'INV/{YYYY}/{MM}/{SEQ}', 'string', 'billing', 'Format Nomor Invoice'],
        ['invoice_number_padding', '3', 'number', 'billing', 'Digit Nomor Urut'],
        ['biaya_admin', '25000', 'number', 'billing', 'Biaya Admin per Invoice'],
        ['ppj_percent', '3', 'number', 'billing', 'PPJ (%)'],
        // Default 0 — isi bila tagihan memang dikenakan PPN.
        ['ppn_percent', '0', 'number', 'billing', 'PPN (%)'],
        // Total dibulatkan ke kelipatan ini; 0 = tanpa pembulatan.
        ['invoice_rounding_to', '100', 'number', 'billing', 'Pembulatan Total (Rp)'],

        // --- Integrasi IoT ---
        ['iot_push_interval_seconds', '60', 'number', 'iot', 'Interval Push Gateway (detik)'],
        ['iot_offline_after_minutes', '5', 'number', 'iot', 'Meter Offline Setelah (menit)'],
        ['iot_retention_months', '24', 'number', 'iot', 'Retensi Data Mentah (bulan)'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as [$key, $value, $type, $group, $label]) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group, 'label' => $label],
            );
        }

        app(SettingService::class)->forget();
    }
}
