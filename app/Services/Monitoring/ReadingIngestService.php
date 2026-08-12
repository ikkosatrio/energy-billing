<?php

namespace App\Services\Monitoring;

use App\Models\MeterReading;
use App\Models\PowerMeter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadingIngestService
{
    /**
     * Menyimpan pembacaan dari gateway.
     *
     * Timestamp yang sudah ada diabaikan, bukan menimpa: gateway kerap
     * mengirim ulang buffer setelah putus jaringan, dan pembacaan yang sudah
     * tercatat adalah data asli yang tidak boleh berubah.
     *
     * @param  array<int, array<string, mixed>>  $readings
     * @return array{stored:int, duplicate:int, latest_read_at:?string}
     */
    public function store(PowerMeter $meter, array $readings): array
    {
        $rows = [];
        $latest = null;

        foreach ($readings as $reading) {
            $readAt = Carbon::parse($reading['read_at']);

            $rows[] = [
                'power_meter_id' => $meter->id,
                'read_at' => $readAt->toDateTimeString(),
                // Pengali CT diterapkan di sini agar kolom stand selalu
                // menyimpan kWh sebenarnya — perhitungan tagihan tidak perlu
                // tahu soal rasio CT.
                'stand_lwbp' => (float) $reading['stand_lwbp'] * (float) $meter->multiplier,
                'stand_wbp' => (float) $reading['stand_wbp'] * (float) $meter->multiplier,
                'active_power_kw' => $reading['active_power_kw'] ?? null,
                'voltage_r' => $reading['voltage_r'] ?? null,
                'voltage_s' => $reading['voltage_s'] ?? null,
                'voltage_t' => $reading['voltage_t'] ?? null,
                'current_r' => $reading['current_r'] ?? null,
                'current_s' => $reading['current_s'] ?? null,
                'current_t' => $reading['current_t'] ?? null,
                'power_factor' => $reading['power_factor'] ?? null,
                'frequency' => $reading['frequency'] ?? null,
                'source' => 'api',
                'raw_payload' => json_encode($reading),
                'created_at' => now()->toDateTimeString(),
            ];

            if (!$latest || $readAt->gt($latest)) {
                $latest = $readAt;
            }
        }

        $before = MeterReading::where('power_meter_id', $meter->id)->count();

        // insertOrIgnore mengandalkan unique (power_meter_id, read_at) untuk
        // membuang duplikat di level database — jauh lebih murah daripada
        // memeriksa satu per satu sebelum menyimpan.
        DB::table('meter_readings')->insertOrIgnore($rows);

        $after = MeterReading::where('power_meter_id', $meter->id)->count();
        $stored = $after - $before;

        $meter->forceFill(['last_seen_at' => now()])->save();

        return [
            'stored' => $stored,
            'duplicate' => count($rows) - $stored,
            'latest_read_at' => $latest?->toDateTimeString(),
        ];
    }
}
