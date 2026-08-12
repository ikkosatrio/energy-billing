<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Membuat satu user Super Admin agar aplikasi bisa dimasuki setelah instalasi.
 *
 * Password diambil dari SEED_ADMIN_PASSWORD di .env. Bila tidak diisi, dipakai
 * nilai default yang sengaja lemah — GANTI setelah login pertama.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('slug', Role::SUPER_ADMIN)->first();

        $user = User::firstOrCreate(
            ['username' => env('SEED_ADMIN_USERNAME', 'admin')],
            [
                'name' => 'Administrator',
                'email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),
                'password' => env('SEED_ADMIN_PASSWORD', 'password'),
                'role_id' => $role?->id,
                'is_active' => true,
            ],
        );

        if ($user->wasRecentlyCreated) {
            $this->command?->warn("User admin dibuat: {$user->username} — segera ganti passwordnya.");
        }
    }
}
