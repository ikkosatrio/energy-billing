<?php

use App\Services\SettingService;

if (!function_exists('setting')) {
    /**
     * Ambil satu nilai dari tabel `settings` (identitas aplikasi, aturan
     * billing, integrasi IoT). Tanpa argumen, mengembalikan seluruh setting.
     *
     *   setting('company_name')
     *   setting('invoice_due_days', 14)
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingService::class);

        return $key === null ? $service->all() : $service->get($key, $default);
    }
}

if (!function_exists('rupiah')) {
    /**
     * Format nominal ke rupiah gaya Indonesia. $withPrefix = false berguna
     * untuk kolom tabel yang sudah punya header "Rp".
     */
    function rupiah(int|float|string|null $value, bool $withPrefix = true): string
    {
        $formatted = number_format((float) $value, 0, ',', '.');

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }
}

if (!function_exists('kwh')) {
    /**
     * Format angka kWh / stand meter dengan pemisah ribuan Indonesia.
     */
    function kwh(int|float|string|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}
