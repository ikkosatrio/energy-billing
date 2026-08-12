<?php

namespace App\Http\Middleware;

use App\Models\PowerMeter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi gateway IoT lewat header X-Device-Key.
 *
 * Key hanya memberi izin MENGIRIM pembacaan untuk satu meter — tidak ada akses
 * baca ke data lain. Meter yang ditemukan ditaruh di atribut request supaya
 * controller tidak perlu mencarinya lagi.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Device-Key') ?: $request->bearerToken();

        if (!$key) {
            return response()->json([
                'message' => 'Header X-Device-Key wajib diisi.',
            ], 401);
        }

        $meter = PowerMeter::where('device_key', $key)->first();

        if (!$meter) {
            return response()->json(['message' => 'Device key tidak dikenal.'], 401);
        }

        // Meter nonaktif berarti sudah dicabut; datanya tidak boleh masuk lagi.
        if ($meter->status === 'inactive') {
            return response()->json(['message' => 'Perangkat berstatus nonaktif.'], 403);
        }

        $request->attributes->set('power_meter', $meter);

        return $next($request);
    }
}
