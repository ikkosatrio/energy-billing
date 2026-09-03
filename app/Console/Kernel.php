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
        /*
         * Agregat harian dijalankan dua lapis.
         *
         * Per menit, hanya hari ini: menyegarkan chart bulan berjalan
         * mendekati real-time. Perintahnya menghitung ulang satu hari penuh
         * tiap kali jalan, jadi dibatasi satu tanggal agar bebannya tidak
         * dobel. Kalau satu run belum selesai saat menit berikutnya tiba,
         * withoutOverlapping melewatinya — jadwalnya efektif menyesuaikan
         * diri dengan kemampuan server, bukan menumpuk.
         *
         * Per jam, kemarin ikut diulang: menangkap pembacaan yang datang
         * terlambat dari gateway, termasuk yang masuk setelah tengah malam.
         *
         * Masa kunci withoutOverlapping dipersempit ke 5 menit; default
         * Laravel 24 jam berarti satu proses yang mati mendadak tanpa
         * melepas kunci bisa mendiamkan agregasi sehari penuh.
         */
        $schedule->command('readings:aggregate --today')
            ->everyMinute()
            ->withoutOverlapping(5);

        $schedule->command('readings:aggregate')
            ->hourly()
            ->withoutOverlapping(5);

        /*
         * Pengejaran mingguan 40 hari ke belakang.
         *
         * Dua jadwal di atas hanya mencakup kemarin dan hari ini, jadi
         * gangguan yang lebih lama dari sehari — server mati, scheduler belum
         * terpasang, atau data yang diimpor belakangan — meninggalkan hari
         * tanpa agregat yang TIDAK PERNAH terkejar sendiri.
         *
         * Akibatnya diam: chart bulanan di Energy History menjumlahkan
         * meter_reading_dailies, sedangkan invoice menghitung ulang dari
         * selisih stand meter. Hari yang bolong membuat chart membaca lebih
         * rendah daripada tagihannya untuk bulan itu, selamanya, tanpa ada
         * yang memberi tahu.
         *
         * 40 hari dipilih agar bulan sebelumnya masih ikut terkoreksi setelah
         * invoicenya terbit. Aman diulang: aggregate() memakai updateOrCreate
         * dan melewati hari tanpa pembacaan, jadi riwayat lama yang mentahnya
         * sudah dibuang retensi tidak ikut tertimpa nol.
         */
        $schedule->command('readings:aggregate', ['--from' => now()->subDays(40)->toDateString()])
            ->weeklyOn(0, '03:00')
            ->withoutOverlapping(30);

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
