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

if (!function_exists('asset_v')) {
    /**
     * URL aset dengan penanda versi dari waktu ubah berkasnya.
     *
     * Memakai config('app.version') yang nilainya tetap membuat browser terus
     * memakai CSS/JS lama setelah deploy — perubahan tampilan baru terlihat
     * kalau pengguna hard-refresh. Dengan filemtime, penandanya berubah sendiri
     * begitu berkasnya berubah.
     *
     * Hasilnya di-cache statis per request karena satu halaman memanggil ini
     * beberapa kali untuk berkas yang sama.
     */
    function asset_v(string $path): string
    {
        static $versions = [];

        if (!isset($versions[$path])) {
            $full = public_path($path);
            $versions[$path] = is_file($full) ? filemtime($full) : config('app.version', '1.0');
        }

        return asset($path).'?v='.$versions[$path];
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

if (!function_exists('kwh_short')) {
    /**
     * Bentuk ringkas untuk label di atas batang chart.
     *
     * Batang pada chart harian bisa tinggal ~28px lebarnya, sementara angka
     * kWh sehari kerap lima digit. Ditulis penuh, label bertabrakan dengan
     * tetangganya dan justru tidak terbaca sama sekali. Angka persisnya tetap
     * tersedia di tooltip tiap batang.
     */
    function kwh_short(int|float|string|null $value): string
    {
        $number = (float) $value;
        // Dibulatkan dulu sebelum dibandingkan: 999,6 ditulis penuh akan
        // menjadi "1.000" yang rancu dengan pemisah ribuan, padahal maksudnya
        // seribu — lebih jelas dibaca sebagai "1,0rb".
        $abs = round(abs($number));

        if ($abs < 1_000) {
            return number_format($number, 0, ',', '.');
        }

        if ($abs < 1_000_000) {
            // Satu desimal hanya di bawah 100 rb — di atas itu tidak menambah
            // informasi apa pun dan hanya memanjangkan label. Perbandingannya
            // memakai nilai yang sudah dibulatkan ke satu desimal, supaya
            // 99.950 tidak lolos sebagai "100,0rb".
            $thousands = $number / 1_000;

            return number_format($thousands, round(abs($thousands), 1) < 100 ? 1 : 0, ',', '.').'rb';
        }

        return number_format($number / 1_000_000, 1, ',', '.').'jt';
    }
}
