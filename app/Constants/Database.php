<?php

namespace App\Constants;

class Database
{
    /**
     * Nama database untuk koneksi 'main' — satu-satunya database aplikasi.
     * Dipakai saat sebuah query perlu menyebut nama database secara eksplisit
     * (mis. raw SQL lintas skema), bukan untuk memilih koneksi.
     */
    public static function main(): string
    {
        return config('database.connections.main.database', 'energy_billing');
    }
}
