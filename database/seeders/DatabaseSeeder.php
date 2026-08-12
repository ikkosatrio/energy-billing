<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seluruh seeder aman dijalankan berulang kali.
     * Urutan penting: role harus ada sebelum user dibuat.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            TariffGroupSeeder::class,
            UserSeeder::class,
        ]);
    }
}
