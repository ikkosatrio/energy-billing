<?php

namespace App\Services\Tariff;

use App\Models\TariffGroup;
use App\Models\TariffRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TariffService
{
    /**
     * Menerbitkan tarif baru untuk sebuah golongan.
     *
     * Tarif lama TIDAK diubah nilainya — hanya ditutup masa berlakunya di hari
     * sebelum tarif baru mulai. Dengan begitu invoice periode lama tetap bisa
     * ditelusuri ke tarif yang benar-benar dipakai saat itu.
     */
    public function publishRate(TariffGroup $group, array $data): TariffRate
    {
        return DB::transaction(function () use ($group, $data) {
            $from = Carbon::parse($data['effective_from'])->startOfDay();

            // Tutup tarif yang masih terbuka dan mulai sebelum tarif baru.
            $group->rates()
                ->whereNull('effective_to')
                ->where('effective_from', '<', $from)
                ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);

            return $group->rates()->create([
                'rate_lwbp' => $data['rate_lwbp'],
                'rate_wbp' => $data['rate_wbp'],
                'rate_beban_per_kva' => $data['rate_beban_per_kva'] ?? 0,
                'effective_from' => $from->toDateString(),
                'effective_to' => null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Tarif yang dipakai untuk sebuah periode tagihan.
     *
     * Acuannya tanggal AKHIR periode: bila tarif berubah di tengah bulan,
     * tagihan bulan itu memakai tarif terbaru yang berlaku pada saat penagihan.
     */
    public function rateForPeriod(TariffGroup $group, string $periodEnd): ?TariffRate
    {
        return $group->rates()->effectiveOn($periodEnd)->first();
    }
}
