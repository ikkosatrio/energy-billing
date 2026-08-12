<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
                $row->key => $this->castValue($row->value, $row->type),
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
