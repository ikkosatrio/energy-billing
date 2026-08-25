<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CustomerUser;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login portal pelanggan — guard 'customer', terpisah dari login staf.
 *
 * Sengaja tidak menumpang Auth\LoginController: setiap pemanggilan Auth di
 * sana memakai guard bawaan, dan menambahkan percabangan guard ke controller
 * yang sama membuat satu kesalahan kecil bisa meloloskan akun portal ke
 * session staf. Duplikasi throttle di sini harganya jauh lebih murah.
 */
class LoginController extends Controller
{
    /** Percobaan login gagal maksimum sebelum dikunci sementara. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 600;

    public function showLoginForm()
    {
        return view('portal.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $field = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $attempted = Auth::guard(CustomerUser::GUARD)->attempt(
            [$field => $credentials['username'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        );

        if (!$attempted) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        /** @var CustomerUser $akun */
        $akun = Auth::guard(CustomerUser::GUARD)->user();

        // Status diperiksa setelah kredensial benar, supaya pesan "nonaktif"
        // tidak bisa dipakai menebak username mana yang valid.
        if (!$akun->is_active) {
            Auth::guard(CustomerUser::GUARD)->logout();
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Akun Anda dinonaktifkan. Hubungi pengelola.',
            ]);
        }

        // Akun tanpa pelanggan terhubung akan mendarat di portal yang kosong
        // seluruhnya, tanpa penjelasan kenapa. Lebih baik ditolak dengan
        // alasan yang bisa ditindaklanjuti.
        if (empty($akun->accessibleCustomerIds())) {
            Auth::guard(CustomerUser::GUARD)->logout();

            throw ValidationException::withMessages([
                'username' => 'Akun Anda belum dihubungkan ke data pelanggan. Hubungi pengelola.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $akun->forceFill(['last_login_at' => now()])->save();

        ActivityLogger::log('login', description: "Akun portal {$akun->username} login.");

        return redirect()->intended(route('portal.dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLogger::log(
            'logout',
            description: 'Akun portal '.Auth::guard(CustomerUser::GUARD)->user()?->username.' logout.',
        );

        Auth::guard(CustomerUser::GUARD)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    /**
     * Kunci throttle diberi awalan 'portal' supaya percobaan login di portal
     * tidak ikut mengunci akun staf yang kebetulan bernama sama, dan
     * sebaliknya.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate('portal|'.Str::lower($request->input('username')).'|'.$request->ip());
    }
}
