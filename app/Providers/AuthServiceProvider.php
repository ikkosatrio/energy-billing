<?php

namespace App\Providers;

use App\Models\User;
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
         * Mengembalikan null (bukan false) saat tidak berizin agar Gate lain
         * dan policy tetap punya kesempatan memutuskan.
         */
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });
    }
}
