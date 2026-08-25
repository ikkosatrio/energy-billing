<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Pengganti ApplicationConfigService milik OEE App, yang dulu membaca
 * identitas aplikasi dari database `apps` terpusat. Aplikasi ini hanya punya
 * satu database, jadi seluruh setting disimpan di tabel `settings` pada
 * koneksi `main` dan dibaca lewat helper setting().
 */
class SettingService
{
    public const CACHE_KEY = 'app_settings';

    /**
     * Setelan yang disimpan terenkripsi.
     *
     * Password SMTP adalah kredensial ke layanan pihak ketiga, bukan
     * konfigurasi biasa: satu dump database yang bocor cukup untuk memakai
     * mail server perusahaan mengirim apa pun atas nama domainnya. Karena itu
     * diperlakukan berbeda dari api_token, yang sengaja tetap terbaca di
     * halaman Setting supaya bisa disalin ke gateway.
     *
     * Daftarnya ditaruh di kode, bukan kolom baru di tabel: hanya kode yang
     * memutuskan kunci mana rahasia, dan nilai di database tidak boleh bisa
     * menurunkan sendiri statusnya jadi tidak-rahasia.
     */
    public const ENCRYPTED_KEYS = ['mail_password'];

    /**
     * Seluruh setting sebagai array key => value yang sudah di-cast.
     * Hasilnya di-cache selamanya dan dibersihkan lewat forget() setiap kali
     * halaman Setting menyimpan perubahan.
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            // Saat instalasi awal tabel settings belum ada (migrate belum
            // jalan). Kembalikan array kosong supaya aplikasi tetap bisa boot
            // dan menjalankan artisan migrate.
            try {
                $rows = DB::connection('main')->table('settings')->get();
            } catch (\Throwable $e) {
                return [];
            }

            return $rows->mapWithKeys(fn ($row) => [
                $row->key => in_array($row->key, self::ENCRYPTED_KEYS, true)
                    ? $this->decrypt($row->value)
                    : $this->castValue($row->value, $row->type),
            ])->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    /**
     * Simpan satu setting lalu buang cache agar nilai baru langsung terpakai.
     */
    public function put(string $key, mixed $value): void
    {
        $encoded = is_array($value) || is_object($value)
            ? json_encode($value)
            : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);

        if (in_array($key, self::ENCRYPTED_KEYS, true) && $encoded !== '') {
            $encoded = Crypt::encryptString($encoded);
        }

        DB::connection('main')->table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $encoded, 'updated_at' => now()],
        );

        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Nilai terenkripsi tidak boleh menjatuhkan seluruh setting.
     *
     * Kalau APP_KEY berganti — pindah server, .env ditulis ulang — nilai lama
     * tidak lagi bisa dibuka. Melempar exception di sini berarti setiap
     * request gagal sebelum halaman apa pun terender, termasuk halaman Setting
     * yang justru dipakai mengisi ulang passwordnya. Dianggap kosong saja:
     * pengiriman email berhenti, sisa aplikasi tetap hidup.
     */
    private function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function castValue(?string $value, ?string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : 0,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }
}
