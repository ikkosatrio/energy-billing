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
| Autentikasi memakai header X-Device-Key milik masing-masing power meter.
|
*/

Route::prefix('v1')->name('api.v1.')->middleware('device')->group(function () {
    Route::get('ping', [MeterReadingController::class, 'ping'])->name('ping');
    Route::post('readings', [MeterReadingController::class, 'store'])->name('readings.store');
});
