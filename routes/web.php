<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Billing\BillingPeriodController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\Master\CustomerController;
use App\Http\Controllers\Master\PowerMeterController;
use App\Http\Controllers\Monitoring\DeviceController;
use App\Http\Controllers\Monitoring\HistoryController;
use App\Http\Controllers\Monitoring\RealtimeController;
use App\Http\Controllers\Portal\DocumentController as PortalDocumentController;
use App\Http\Controllers\Portal\LoginController as PortalLoginController;
use App\Http\Controllers\Portal\PageController as PortalPageController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\System\ActivityLogController;
use App\Http\Controllers\System\CustomerUserController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\System\TrialDataWipeController;
use App\Http\Controllers\System\UserController;
use App\Http\Controllers\Tariff\TariffGroupController;
use App\Http\Controllers\Tariff\TariffScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Rute per modul ditambahkan seiring modulnya dibangun. config/menu.php
| melewati item yang route-nya belum terdaftar, jadi sidebar tetap utuh
| walau halamannya menyusul.
|
*/

// Health check container (dipakai Docker HEALTHCHECK).
Route::get('/health', fn () => response('OK', 200));

/*
 * Guard ditulis eksplisit ('web'), tidak dibiarkan mengikuti guard default.
 *
 * `auth` tanpa argumen memakai guard default, dan sejak ada guard kedua nilai
 * itu bisa bergeser dalam satu request (mis. Auth::shouldUse). Beberapa route
 * staf — /dashboard dan /panduan — sengaja tanpa `can:` apa pun, jadi guard
 * itulah satu-satunya penahannya: begitu ia bergeser, panel staf terbuka
 * untuk akun portal.
 */
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Portal Pelanggan
|--------------------------------------------------------------------------
|
| Guard 'customer', bukan 'web'. Ditulis di blok tersendiri di atas modul staf
| supaya tidak ada satu pun route portal yang bisa ikut terbawa ke dalam grup
| middleware('auth') di bawah — di sana guard-nya staf, dan akun portal akan
| ditolak dengan pesan yang menyesatkan.
|
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [PortalLoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [PortalLoginController::class, 'login']);
    });

    Route::middleware('auth:customer')->group(function () {
        Route::post('logout', [PortalLoginController::class, 'logout'])->name('logout');

        Route::get('/', [PortalPageController::class, 'dashboard'])->name('dashboard');

        Route::middleware('can:portal.monitoring.view')->group(function () {
            Route::get('monitoring', [PortalPageController::class, 'monitoring'])->name('monitoring');
            Route::get('riwayat', [PortalPageController::class, 'history'])->name('history');
        });

        Route::get('pemakaian', [PortalPageController::class, 'usage'])
            ->middleware('can:portal.usage.view')->name('usage');

        Route::middleware('can:portal.invoice.view')->group(function () {
            Route::get('invoice', [PortalPageController::class, 'invoices'])->name('invoices');
            Route::get('invoice/{invoice}/unduh', [PortalDocumentController::class, 'invoice'])
                ->name('invoices.download');
            Route::get('invoice/{invoice}/pratinjau', [PortalDocumentController::class, 'invoicePreview'])
                ->name('invoices.preview');
        });

        Route::middleware('can:portal.payment.view')->group(function () {
            Route::get('pembayaran', [PortalPageController::class, 'payments'])->name('payments');
            Route::get('pembayaran/{payment}/kuitansi', [PortalDocumentController::class, 'receipt'])
                ->name('payments.receipt');
            Route::get('pembayaran/{payment}/kuitansi/pratinjau', [PortalDocumentController::class, 'receiptPreview'])
                ->name('payments.receipt.preview');
        });
    });
});

Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Buku Panduan — sengaja tanpa syarat izin: staf baru justru yang paling
    // membutuhkannya, dan merekalah yang izinnya paling sedikit.
    Route::get('/panduan', [GuideController::class, 'show'])->name('guide.show');
    Route::get('/panduan/unduh', [GuideController::class, 'download'])->name('guide.download');

    // ── Monitoring ───────────────────────────────────────────────────────
    Route::prefix('monitoring')->name('monitoring.')->middleware('can:monitoring.view')->group(function () {
        Route::get('realtime', [RealtimeController::class, 'index'])->name('realtime');
        Route::get('history', [HistoryController::class, 'index'])->name('history');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices');
    });

    // ── Billing & Invoice ────────────────────────────────────────────────
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('periods', [BillingPeriodController::class, 'index'])
            ->middleware('can:invoice.generate')->name('periods.index');

        Route::middleware('can:invoice.view')->group(function () {
            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
            Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        });

        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])
            ->middleware('can:invoice.send')->name('invoices.send');

        Route::get('payments', [PaymentController::class, 'index'])
            ->middleware('can:payment.view')->name('payments.index');

        Route::get('payments/import-template', [PaymentController::class, 'template'])
            ->middleware('can:payment.bulk')->name('payments.template');

        Route::middleware('can:payment.receipt')->group(function () {
            Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])
                ->name('payments.receipt');
            Route::get('payments/{payment}/receipt/preview', [PaymentController::class, 'receiptPreview'])
                ->name('payments.receipt.preview');
        });
    });

    // ── Master Data ──────────────────────────────────────────────────────
    Route::prefix('master')->name('master.')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('can:customer.view')->name('customers.index');

        Route::get('meters', [PowerMeterController::class, 'index'])
            ->middleware('can:meter.view')->name('meters.index');
    });

    // ── Tarif & Konfigurasi ──────────────────────────────────────────────
    Route::prefix('tariff')->name('tariff.')->group(function () {
        Route::get('groups', [TariffGroupController::class, 'index'])
            ->middleware('can:tariff.view')->name('groups.index');

        Route::get('schedules', [TariffScheduleController::class, 'index'])
            ->middleware('can:tariff.view')->name('schedules.index');
    });

    // ── Report ───────────────────────────────────────────────────────────
    Route::prefix('report')->name('report.')->middleware('can:report.view')->group(function () {
        Route::get('usage', [ReportController::class, 'usage'])->name('usage');
        Route::get('billing', [ReportController::class, 'billing'])->name('billing');
        Route::get('payments', [ReportController::class, 'payments'])->name('payments');
        Route::get('readings', [ReportController::class, 'readings'])->name('readings');
        Route::get('export/{type}/{format}', [ReportController::class, 'export'])->name('export');
    });

    // ── Sistem ───────────────────────────────────────────────────────────
    Route::prefix('system')->name('system.')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])
            ->middleware('can:setting.manage')->name('settings.index');

        Route::get('users', [UserController::class, 'index'])
            ->middleware('can:user.view')->name('users.index');

        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('can:role.view')->name('roles.index');

        Route::get('customer-users', [CustomerUserController::class, 'index'])
            ->middleware('can:customer_user.view')->name('customer-users.index');

        Route::get('activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('can:activity_log.view')->name('activity-logs.index');

        Route::get('trial-data', [TrialDataWipeController::class, 'index'])
            ->middleware('can:reading.wipe_trial')->name('trial-data.index');
    });
});
