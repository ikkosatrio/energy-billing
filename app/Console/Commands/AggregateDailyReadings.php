<?php

namespace App\Console\Commands;

use App\Services\Monitoring\DailyAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateDailyReadings extends Command
{
    protected $signature = 'readings:aggregate
                            {--date= : Tanggal yang diringkas (Y-m-d). Default: kemarin dan hari ini.}
                            {--today : Hanya hari ini, tanpa mengulang kemarin.}';

    protected $description = 'Meringkas pembacaan meter menjadi agregat harian';

    public function handle(DailyAggregationService $service): int
    {
        /*
         * Tanpa opsi apa pun, hari ini ikut diproses agar chart bulan berjalan
         * sudah terisi sebelum tengah malam; kemarin diproses ulang untuk
         * menangkap pembacaan yang datang terlambat dari gateway.
         *
         * --today memangkas kemarin, jadi bebannya separuh. Dipakai oleh
         * jadwal per menit, yang hanya perlu menyegarkan hari berjalan —
         * pass per jam yang mengurus data telat. Tanggalnya ditentukan di
         * sini, bukan di-hardcode ke jadwal, supaya tidak ada tanggal basi
         * yang terbawa kalau prosesnya hidup melewati tengah malam.
         */
        $dates = match (true) {
            (bool) $this->option('date') => [Carbon::parse($this->option('date'))],
            (bool) $this->option('today') => [Carbon::today()],
            default => [Carbon::yesterday(), Carbon::today()],
        };

        foreach ($dates as $date) {
            $count = $service->aggregateAll($date);
            $this->info("{$date->toDateString()}: {$count} meter diringkas.");
        }

        return self::SUCCESS;
    }
}
