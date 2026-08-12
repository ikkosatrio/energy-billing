<?php

namespace App\Console\Commands;

use App\Models\MeterReading;
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

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: setting('iot_retention_months', 24));

        if ($months < 1) {
            $this->warn('Retensi tidak diatur (< 1 bulan). Tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMonths($months);
        $total = 0;

        // Dihapus bertahap agar tidak mengunci tabel terlalu lama —
        // tabel ini bisa berisi jutaan baris.
        do {
            $deleted = MeterReading::where('read_at', '<', $cutoff)->limit(5000)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("{$total} pembacaan sebelum {$cutoff->toDateString()} dihapus.");

        return self::SUCCESS;
    }
}
