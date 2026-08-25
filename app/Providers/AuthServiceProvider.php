<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        //
    ];

    public function boot(): void
    {
        /*
         * Menjembatani permission berbasis slug ke sistem Gate bawaan Laravel,
         * sehingga slug dari tabel `permissions` bisa langsung dipakai sebagai
         * ability:
         *
         *   @can('invoice.generate') ... @endcan
         *   ->middleware('can:invoice.generate')
         *
         * Berlaku untuk KEDUA guard. Middleware `auth:customer` memanggil
         * Auth::shouldUse('customer'), jadi di dalam request portal Gate
         * menilai CustomerUser — bukan session staf.
         *
         * Parameternya sengaja tidak di-hint App\Models\User: hanya ada satu
         * closure Gate::before untuk seluruh aplikasi, dan hint itu membuat
         * setiap request portal gagal dengan TypeError sebelum halamannya
         * sempat dirender.
         *
         * Mengembalikan null (bukan false) saat tidak berizin agar Gate lain
         * dan policy tetap punya kesempatan memutuskan.
         */
        Gate::before(function (Authenticatable $user, string $ability) {
            if (!method_exists($user, 'hasPermission')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
