<?php

namespace App\Services\Monitoring;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use Illuminate\Support\Carbon;

/**
 * Meringkas pembacaan mentah menjadi satu baris per meter per hari.
 *
 * Tanpa ini, chart bulanan/tahunan harus memindai jutaan baris
 * `meter_readings` (±43.200 baris per meter per bulan pada interval 1 menit).
 */
class DailyAggregationService
{
    /**
     * Menghitung ulang ringkasan satu meter untuk satu tanggal.
     * Aman diulang: memakai updateOrCreate, jadi menjalankan ulang untuk
     * tanggal yang sama akan memperbaiki angkanya, bukan menggandakan.
     */
    public function aggregate(PowerMeter $meter, Carbon $date): ?MeterReadingDaily
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $readings = MeterReading::where('power_meter_id', $meter->id)
            ->between($start->toDateTimeString(), $end->toDateTimeString())
            ->orderBy('read_at')
            ->get(['read_at', 'stand_lwbp', 'stand_wbp', 'active_power_kw']);

        if ($readings->isEmpty()) {
            return null;
        }

        $first = $readings->first();
        $last = $readings->last();
        $peak = $readings->whereNotNull('active_power_kw')->sortByDesc('active_power_kw')->first();

        return MeterReadingDaily::updateOrCreate(
            ['power_meter_id' => $meter->id, 'date' => $date->toDateString()],
            [
                'stand_lwbp_start' => $first->stand_lwbp,
                'stand_lwbp_end' => $last->stand_lwbp,
                'stand_wbp_start' => $first->stand_wbp,
                'stand_wbp_end' => $last->stand_wbp,
                'kwh_lwbp' => $this->delta($first->stand_lwbp, $last->stand_lwbp),
                'kwh_wbp' => $this->delta($first->stand_wbp, $last->stand_wbp),
                'peak_kw' => $peak?->active_power_kw,
                'peak_at' => $peak?->read_at,
                'reading_count' => $readings->count(),
            ],
        );
    }

    /**
     * Menghitung ulang seluruh meter untuk satu tanggal.
     *
     * @return int Jumlah meter yang punya data pada tanggal tersebut.
     */
    public function aggregateAll(Carbon $date): int
    {
        $count = 0;

        PowerMeter::where('status', '!=', 'inactive')
            ->select(['id', 'status'])
            ->chunkById(50, function ($meters) use ($date, &$count) {
                foreach ($meters as $meter) {
                    if ($this->aggregate($meter, $date)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * Selisih dua stand kumulatif.
     *
     * Hasil negatif berarti meter di-reset atau angkanya berputar kembali ke
     * nol. Nilainya dinolkan, bukan dibiarkan negatif, agar tidak mengurangi
     * total pemakaian bulan berjalan — kasus seperti ini perlu dikoreksi
     * manual lewat catatan pada invoice.
     */
    private function delta(float|string $start, float|string $end): float
    {
        return max(0, (float) $end - (float) $start);
    }
}
