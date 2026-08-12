<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Percobaan login gagal maksimum sebelum dikunci sementara. */
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 600;

    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Login memakai username ATAU email, dengan password ter-hash bcrypt.
     * Sistem lama membandingkan password plaintext dan menembak LDAP Avian —
     * keduanya sudah dibuang.
     */
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

        $attempted = Auth::attempt(
            [$field => $credentials['username'], 'password' => $credentials['password']],
            $request->boolean('remember'),
        );

        if (!$attempted) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Cek status setelah kredensial benar, supaya pesan "nonaktif" tidak
        // bisa dipakai menebak username mana yang valid.
        if (!$user->is_active) {
            Auth::logout();
            RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        ActivityLogger::log('login', description: "User {$user->username} login.");

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLogger::log('logout', description: 'User '.$request->user()?->username.' logout.');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Kunci throttle per kombinasi username + IP, sehingga serangan terhadap
     * satu akun tidak ikut mengunci pengguna lain di jaringan yang sama.
     */
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('username')).'|'.$request->ip());
    }
}
