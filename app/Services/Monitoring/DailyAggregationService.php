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
    public function __construct(private readonly ConsumptionCalculator $consumption)
    {
    }

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

        $last = $readings->last();
        $peak = $readings->whereNotNull('active_power_kw')->sortByDesc('active_power_kw')->first();

        /*
         * Titik awal hari ini adalah pembacaan TERAKHIR SEBELUM hari ini,
         * bukan pembacaan pertama hari ini.
         *
         * Tanpa itu, pemakaian antara pembacaan terakhir kemarin (mis. 23:30)
         * dan pembacaan pertama hari ini (00:00) tidak masuk ke hari mana pun.
         * Hilangnya satu interval setiap hari — pada interval 30 menit itu
         * berarti 15 jam pemakaian per bulan, dan membuat Rekap Pemakaian
         * membaca ~2% lebih rendah daripada yang ditagihkan invoice.
         *
         * Dengan pembacaan pembuka ini, jumlah agregat harian sepanjang periode
         * sama persis dengan selisih stand yang dipakai invoice.
         */
        $opening = MeterReading::where('power_meter_id', $meter->id)
            ->where('read_at', '<', $start->toDateTimeString())
            ->orderByDesc('read_at')
            ->first(['read_at', 'stand_lwbp', 'stand_wbp']);

        // collect()->prepend() memutasi koleksi aslinya, sehingga reading_count
        // ikut bertambah bila dipakai langsung. Koleksi baru dibuat terpisah.
        $series = $opening ? collect([$opening])->concat($readings) : $readings;

        // Dihitung dari penjumlahan selisih antar pembacaan, bukan sekadar
        // stand akhir dikurangi stand awal. Bedanya terasa saat meter di-reset
        // di tengah hari: cara lama menghasilkan 0 dan menghapus seluruh
        // pemakaian hari itu.
        $usage = $this->consumption->fromReadings($series, $meter->effective_stand_max);

        // Stand awal ikut memakai pembacaan pembuka, sehingga
        // `stand_akhir - stand_awal` tetap konsisten dengan kolom kWh.
        $first = $opening ?? $readings->first();

        return MeterReadingDaily::updateOrCreate(
            ['power_meter_id' => $meter->id, 'date' => $date->toDateString()],
            [
                'stand_lwbp_start' => $first->stand_lwbp,
                'stand_lwbp_end' => $last->stand_lwbp,
                'stand_wbp_start' => $first->stand_wbp,
                'stand_wbp_end' => $last->stand_wbp,
                'kwh_lwbp' => $usage['lwbp'],
                'kwh_wbp' => $usage['wbp'],
                'peak_kw' => $peak?->active_power_kw,
                'peak_at' => $peak?->read_at,
                'reading_count' => $readings->count(),
                'reset_count' => $usage['reset_count'] + $usage['rollover_count'],
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

}
