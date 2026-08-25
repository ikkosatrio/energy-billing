<?php

namespace App\Http\Middleware;

use App\Models\CustomerUser;
use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * Tujuan redirect mengikuti guard yang cocok, bukan satu konstanta:
     * akun portal yang membuka halaman login harus mendarat di dashboard
     * portal. Tanpa ini ia dilempar ke /dashboard staf dan langsung
     * ditolak 403 — terbaca seolah akunnya bermasalah.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect($guard === CustomerUser::GUARD
                    ? route('portal.dashboard')
                    : RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
