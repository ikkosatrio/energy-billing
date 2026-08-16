<?php

namespace App\Services\Monitoring;

use App\Models\PowerMeter;
use App\Models\PowerMeterStatus;
use Illuminate\Support\Carbon;

class DeviceStatusService
{
    /**
     * Menimpa kondisi terakhir sebuah meter.
     *
     * Tidak ada riwayat yang ditulis — untuk itu gateway memakai endpoint
     * pembacaan. Kiriman status juga menandai perangkat masih hidup, sehingga
     * gateway yang mengirim status lebih sering daripada pembacaan tetap
     * terbaca online.
     *
     * @param  array<string, mixed>  $payload
     */
    public function store(PowerMeter $meter, array $payload): PowerMeterStatus
    {
        // Nilai yang tidak dikirim dibiarkan apa adanya, bukan ditimpa null:
        // gateway boleh mengirim status ringkas (mis. hanya sinyal dan IP)
        // tanpa menghapus kondisi kelistrikan yang sudah tercatat.
        $data = array_filter(
            [
                'signal_dbm' => $payload['signal_dbm'] ?? null,
                'ip_address' => $payload['ip_address'] ?? null,
                'mac_address' => $payload['mac_address'] ?? null,
                'firmware_version' => $payload['firmware_version'] ?? null,
                'stand_lwbp' => $this->scaled($payload['stand_lwbp'] ?? null, $meter),
                'stand_wbp' => $this->scaled($payload['stand_wbp'] ?? null, $meter),
                'active_power_kw' => $payload['active_power_kw'] ?? null,
                'voltage_r' => $payload['voltage_r'] ?? null,
                'voltage_s' => $payload['voltage_s'] ?? null,
                'voltage_t' => $payload['voltage_t'] ?? null,
                'current_r' => $payload['current_r'] ?? null,
                'current_s' => $payload['current_s'] ?? null,
                'current_t' => $payload['current_t'] ?? null,
                'power_factor' => $payload['power_factor'] ?? null,
                'frequency' => $payload['frequency'] ?? null,
            ],
            fn ($value) => $value !== null,
        );

        $data['read_at'] = isset($payload['read_at'])
            ? Carbon::parse($payload['read_at'])->toDateTimeString()
            : now()->toDateTimeString();

        $status = PowerMeterStatus::updateOrCreate(['power_meter_id' => $meter->id], $data);

        $meter->forceFill(['last_seen_at' => now()])->save();

        return $status;
    }

    /**
     * Stand disimpan dalam satuan yang sama seperti pada `meter_readings` —
     * sudah dikali rasio CT — supaya angkanya bisa dibandingkan langsung.
     */
    private function scaled(mixed $value, PowerMeter $meter): ?float
    {
        return $value === null ? null : (float) $value * (float) $meter->multiplier;
    }
}
