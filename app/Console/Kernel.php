<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Agregat harian menyegarkan chart bulan berjalan sekaligus menangkap
        // pembacaan yang datang terlambat dari gateway.
        $schedule->command('readings:aggregate')
            ->hourly()
            ->withoutOverlapping();

        // Generate invoice dijalankan tiap hari karena tanggal tagih boleh
        // berbeda per pelanggan; perintahnya sendiri yang menentukan pelanggan
        // mana yang jatuh tempo hari itu.
        $schedule->command('invoices:generate')
            ->dailyAt(setting('billing_generate_time', '00:15'))
            ->withoutOverlapping();

        // Menandai invoice yang lewat jatuh tempo.
        $schedule->command('invoices:mark-overdue')->dailyAt('01:00');

        // Kuitansi dikirim setelah masa tunggu, memberi jeda untuk menarik
        // pembayaran yang ternyata salah input sebelum dokumennya beredar.
        $schedule->command('receipts:send-due')->dailyAt('07:00')->withoutOverlapping();

        // Membuang pembacaan mentah yang sudah lewat masa retensi. Agregat
        // harian tetap disimpan, jadi riwayat jangka panjang tidak hilang.
        $schedule->command('readings:prune')->weeklyOn(0, '02:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
