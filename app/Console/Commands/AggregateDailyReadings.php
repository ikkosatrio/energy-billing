<?php

namespace App\Console\Commands;

use App\Services\Monitoring\DailyAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateDailyReadings extends Command
{
    protected $signature = 'readings:aggregate
                            {--date= : Tanggal yang diringkas (Y-m-d). Default: kemarin dan hari ini.}';

    protected $description = 'Meringkas pembacaan meter menjadi agregat harian';

    public function handle(DailyAggregationService $service): int
    {
        // Tanpa --date, hari ini ikut diproses agar chart bulan berjalan
        // sudah terisi sebelum tengah malam; kemarin diproses ulang untuk
        // menangkap pembacaan yang datang terlambat dari gateway.
        $dates = $this->option('date')
            ? [Carbon::parse($this->option('date'))]
            : [Carbon::yesterday(), Carbon::today()];

        foreach ($dates as $date) {
            $count = $service->aggregateAll($date);
            $this->info("{$date->toDateString()}: {$count} meter diringkas.");
        }

        return self::SUCCESS;
    }
}
