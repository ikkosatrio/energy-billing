<?php

use App\Http\Controllers\Api\V1\MeterReadingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Endpoint yang dikonsumsi gateway IoT. Diletakkan di sini (bukan web.php)
| supaya tidak melewati middleware session/CSRF.
|
| Meter ditentukan lewat `meter_id` pada payload — ID yang tampil di halaman
| Power Meter Device. Autentikasi memakai satu API token global dari setting
| sistem, dikirim lewat header X-Api-Token.
|
| Dokumentasi interaktif: /api/documentation
|
*/

Route::prefix('v1')->name('api.v1.')->middleware('gateway')->group(function () {
    Route::get('ping', [MeterReadingController::class, 'ping'])->name('ping');
    Route::get('meters', [MeterReadingController::class, 'meters'])->name('meters.index');
    Route::post('readings', [MeterReadingController::class, 'store'])->name('readings.store');

    // Kondisi terakhir perangkat — menimpa, tidak dicatat sebagai riwayat.
    Route::post('status', [MeterReadingController::class, 'status'])->name('status.store');
});
