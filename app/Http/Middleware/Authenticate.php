<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Halaman login mana yang dituju saat belum terautentikasi.
     *
     * Dibedakan per area: pengunjung portal yang sesinya habis harus kembali
     * ke login portal, bukan ke login staf — di sana ia tidak punya akun sama
     * sekali, jadi form staf hanya jadi jalan buntu.
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson()) {
            return null;
        }

        return $request->is('portal', 'portal/*')
            ? route('portal.login')
            : route('login');
    }
}
