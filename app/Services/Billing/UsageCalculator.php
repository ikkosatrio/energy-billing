<?php

namespace App\Services\Billing;

use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Services\Monitoring\ConsumptionCalculator;
use Illuminate\Support\Carbon;

/**
 * Menghitung pemakaian kWh sebuah meter dalam satu periode.
 *
 * Pemakaian = stand AKHIR periode − stand AWAL periode.
 *
 * Sengaja tidak menjumlahkan delta antar pembacaan: bila gateway sempat
 * offline di tengah periode, penjumlahan delta akan kehilangan pemakaian yang
 * terjadi selama offline, sedangkan selisih stand tetap menangkapnya karena
 * angka meter terus berjalan.
 */
class UsageCalculator
{
    public function __construct(private readonly ConsumptionCalculator $consumption)
    {
    }

    /**
     * @return array{
     *     stand_lwbp_start:float, stand_lwbp_end:float, kwh_lwbp:float,
     *     stand_wbp_start:float, stand_wbp_end:float, kwh_wbp:float,
     *     first_read_at:?Carbon, last_read_at:?Carbon, has_data:bool, meter_reset:bool
     * }
     */
    public function forPeriod(PowerMeter $meter, Carbon $start, Carbon $end): array
    {
        $last = MeterReading::where('power_meter_id', $meter->id)
            ->lastUntil($end->copy()->endOfDay()->toDateTimeString())
            ->where('read_at', '>=', $start->copy()->startOfDay()->toDateTimeString())
            ->first();

        if (!$last) {
            return $this->empty();
        }

        /*
         * Stand awal diambil dari pembacaan TERAKHIR SEBELUM periode ini —
         * yaitu stand akhir periode sebelumnya, sama seperti cara tagihan
         * listrik pada umumnya.
         *
         * Memakai pembacaan pertama DI DALAM periode akan kehilangan pemakaian
         * antara pergantian periode dan pembacaan pertama itu, dan membuat
         * angkanya berbeda dengan jumlah agregat harian yang dipakai laporan.
         *
         * Pelanggan baru belum punya pembacaan sebelumnya; untuk mereka
         * pembacaan pertama di dalam periode yang menjadi titik awal.
         */
        $first = MeterReading::where('power_meter_id', $meter->id)
            ->where('read_at', '<', $start->copy()->startOfDay()->toDateTimeString())
            ->orderByDesc('read_at')
            ->first()
            ?? MeterReading::where('power_meter_id', $meter->id)
                ->firstFrom($start->copy()->startOfDay()->toDateTimeString())
                ->where('read_at', '<=', $end->copy()->endOfDay()->toDateTimeString())
                ->first();

        if (!$first) {
            return $this->empty();
        }

        $lwbpStart = (float) $first->stand_lwbp;
        $lwbpEnd = (float) $last->stand_lwbp;
        $wbpStart = (float) $first->stand_wbp;
        $wbpEnd = (float) $last->stand_wbp;

        /*
         * Menentukan apakah selisih stand awal-akhir masih boleh dipercaya.
         *
         * Membandingkan stand pertama dengan stand terakhir saja TIDAK cukup.
         * Meter yang di-reset berkali-kali dalam satu periode bisa berakhir di
         * angka yang lebih tinggi daripada awalnya — misalnya 0 → naik → reset,
         * berulang 100 kali, lalu berhenti di 20. Pemeriksaan akhir-vs-awal
         * membacanya sebagai normal dan menagih 20 kWh dari 5.020 kWh yang
         * sebenarnya terpakai.
         *
         * Karena itu agregat harian ikut diperiksa: kolom reset_count-nya
         * dihitung dengan menelusuri seluruh pembacaan hari itu, jadi reset di
         * tengah periode tetap tertangkap. Query-nya murah — satu baris per
         * hari, bukan per pembacaan.
         */
        $daily = MeterReadingDaily::where('power_meter_id', $meter->id)
            ->between($start->toDateString(), $end->toDateString())
            ->selectRaw('COALESCE(SUM(reset_count), 0) AS resets, COUNT(*) AS hari')
            ->first();

        $reset = $lwbpEnd < $lwbpStart
            || $wbpEnd < $wbpStart
            || (int) ($daily->resets ?? 0) > 0
            // Tanpa agregat harian, tidak ada cara murah memastikan tidak ada
            // reset di tengah periode. Tempuh jalur teliti daripada menagih
            // angka yang belum tentu benar.
            || (int) ($daily->hari ?? 0) === 0;

        if ($reset) {
            // Jalur teliti: pemakaian dijumlahkan dari selisih antar pembacaan
            // sepanjang periode, sehingga setiap reset ikut terhitung.
            // Pembacaan pembuka ikut disertakan agar pemakaian di pergantian
            // periode tidak hilang — sama seperti pada agregat harian.
            $usage = $this->consumption->fromReadings(
                collect([$first])->concat(
                    MeterReading::where('power_meter_id', $meter->id)
                        ->between($start->copy()->startOfDay()->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString())
                        ->orderBy('read_at')
                        ->get(['read_at', 'stand_lwbp', 'stand_wbp'])
                )->unique(fn ($r) => $r->read_at->toDateTimeString())->values(),
                $meter->effective_stand_max,
            );

            $kwhLwbp = $usage['lwbp'];
            $kwhWbp = $usage['wbp'];

            // Hanya ditandai bila memang ada stand yang mundur. Periode tanpa
            // agregat harian menempuh jalur yang sama, tapi tidak perlu
            // memunculkan peringatan reset pada invoice.
            $reset = ($usage['reset_count'] + $usage['rollover_count']) > 0;
        } else {
            $kwhLwbp = $lwbpEnd - $lwbpStart;
            $kwhWbp = $wbpEnd - $wbpStart;
        }

        return [
            'stand_lwbp_start' => $lwbpStart,
            'stand_lwbp_end' => $lwbpEnd,
            'kwh_lwbp' => max(0, $kwhLwbp),
            'stand_wbp_start' => $wbpStart,
            'stand_wbp_end' => $wbpEnd,
            'kwh_wbp' => max(0, $kwhWbp),
            'first_read_at' => $first->read_at,
            'last_read_at' => $last->read_at,
            'has_data' => true,
            'meter_reset' => $reset,
        ];
    }

    private function empty(): array
    {
        return [
            'stand_lwbp_start' => 0.0,
            'stand_lwbp_end' => 0.0,
            'kwh_lwbp' => 0.0,
            'stand_wbp_start' => 0.0,
            'stand_wbp_end' => 0.0,
            'kwh_wbp' => 0.0,
            'first_read_at' => null,
            'last_read_at' => null,
            'has_data' => false,
            'meter_reset' => false,
        ];
    }
}
