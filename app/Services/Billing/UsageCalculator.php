<?php

namespace App\Services\Billing;

use App\Models\MeterReading;
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
        $first = MeterReading::where('power_meter_id', $meter->id)
            ->firstFrom($start->copy()->startOfDay()->toDateTimeString())
            ->where('read_at', '<=', $end->copy()->endOfDay()->toDateTimeString())
            ->first();

        $last = MeterReading::where('power_meter_id', $meter->id)
            ->lastUntil($end->copy()->endOfDay()->toDateTimeString())
            ->where('read_at', '>=', $start->copy()->startOfDay()->toDateTimeString())
            ->first();

        if (!$first || !$last) {
            return $this->empty();
        }

        $lwbpStart = (float) $first->stand_lwbp;
        $lwbpEnd = (float) $last->stand_lwbp;
        $wbpStart = (float) $first->stand_wbp;
        $wbpEnd = (float) $last->stand_wbp;

        // Stand yang mundur menandakan meter di-reset atau berputar kembali ke
        // nol. Selisih stand awal-akhir tidak lagi mewakili pemakaian.
        $reset = $lwbpEnd < $lwbpStart || $wbpEnd < $wbpStart;

        if ($reset) {
            // Jalur lambat, hanya ditempuh saat ada reset: pemakaian dihitung
            // dari penjumlahan selisih antar pembacaan sepanjang periode.
            //
            // Sebelumnya kasus ini menghasilkan 0 — pelanggan yang meternya
            // sempat di-reset praktis tidak ditagih atas pemakaiannya.
            $usage = $this->consumption->fromReadings(
                MeterReading::where('power_meter_id', $meter->id)
                    ->between($start->copy()->startOfDay()->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString())
                    ->orderBy('read_at')
                    ->get(['read_at', 'stand_lwbp', 'stand_wbp']),
                $meter->effective_stand_max,
            );

            $kwhLwbp = $usage['lwbp'];
            $kwhWbp = $usage['wbp'];
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
