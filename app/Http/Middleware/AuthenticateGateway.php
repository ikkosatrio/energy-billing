<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi gateway IoT lewat satu API token global.
 *
 * Token disimpan di setting sistem (`api_token`) dan bisa dilihat, disalin,
 * serta digenerate ulang kapan saja dari halaman Setting — berbeda dari
 * device_key per perangkat sebelumnya yang hanya tampil sekali.
 *
 * Meter yang dituju ditentukan oleh `meter_id` pada payload, bukan oleh token.
 */
class AuthenticateGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) setting('api_token', '');

        // Token kosong berarti autentikasi memang dimatikan (mis. server
        // benar-benar tertutup di jaringan internal).
        if ($expected === '') {
            return $next($request);
        }

        $provided = (string) ($request->header('X-Api-Token') ?: $request->bearerToken() ?: '');

        // hash_equals mencegah kebocoran informasi lewat selisih waktu
        // perbandingan string.
        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'API token tidak valid. Kirim lewat header X-Api-Token.',
            ], 401);
        }

        return $next($request);
    }
}
