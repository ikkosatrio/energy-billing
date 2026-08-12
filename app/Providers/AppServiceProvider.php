<?php

namespace App\Providers;

use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu instance per request agar setting hanya dibaca/di-cache sekali.
        $this->app->singleton(SettingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Locale aplikasi tidak otomatis diteruskan ke Carbon, sehingga
        // translatedFormat() akan tetap memakai bahasa Inggris tanpa baris ini.
        Carbon::setLocale(config('app.locale'));

        // Paginator bawaan memakai markup Tailwind yang mengandalkan preflight
        // (dimatikan di project ini). resources/views/vendor/pagination/default
        // memakai kelas design system sendiri.
        Paginator::defaultView('vendor.pagination.default');
    }
}
