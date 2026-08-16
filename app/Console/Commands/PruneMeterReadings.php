<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\Monitoring\DataRetentionService;
use Illuminate\Console\Command;

/**
 * Membuang pembacaan mentah yang sudah lewat masa retensi (setting
 * `iot_retention_months`). Agregat harian tidak ikut dihapus, sehingga chart
 * bulanan dan laporan periode lama tetap utuh.
 */
class PruneMeterReadings extends Command
{
    protected $signature = 'readings:prune {--months= : Retensi dalam bulan; default dari setting sistem.}';

    protected $description = 'Menghapus pembacaan meter mentah yang sudah lewat masa retensi';

    public function handle(DataRetentionService $retention): int
    {
        $months = $this->option('months') !== null ? (int) $this->option('months') : $retention->retentionMonths();

        if ($months < 1) {
            $this->warn('Retensi tidak diatur (< 1 bulan). Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $cutoff = $retention->cutoff($months);
        $total = $retention->purge(months: $months);

        $this->info("{$total} pembacaan sebelum {$cutoff->toDateString()} dihapus.");

        // Dicatat walau berjalan tanpa user (dari scheduler) — jejak audit
        // untuk penghapusan massal ini tetap perlu terlihat di aplikasi,
        // bukan cuma di output console server yang jarang ada yang membaca.
        if ($total > 0) {
            ActivityLogger::log(
                'pruned',
                description: "Hapus otomatis {$total} pembacaan mentah sebelum {$cutoff->toDateString()} (retensi {$months} bulan)",
            );
        }

        return self::SUCCESS;
    }
}
