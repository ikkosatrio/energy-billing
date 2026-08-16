<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kondisi terakhir sebuah power meter. Selalu ditimpa, tidak pernah bertambah.
 * Untuk riwayat, lihat MeterReading.
 */
class PowerMeterStatus extends Model
{
    use HasFactory;

    protected $primaryKey = 'power_meter_id';

    public $incrementing = false;

    // Baris ini tidak pernah "dibuat ulang", hanya diperbarui — created_at
    // tidak menambah informasi apa pun.
    public const CREATED_AT = null;

    protected $fillable = [
        'power_meter_id',
        'signal_dbm',
        'ip_address',
        'mac_address',
        'firmware_version',
        'stand_lwbp',
        'stand_wbp',
        'active_power_kw',
        'voltage_r',
        'voltage_s',
        'voltage_t',
        'current_r',
        'current_s',
        'current_t',
        'power_factor',
        'frequency',
        'read_at',
    ];

    protected $casts = [
        'signal_dbm' => 'integer',
        'stand_lwbp' => 'decimal:2',
        'stand_wbp' => 'decimal:2',
        'active_power_kw' => 'decimal:2',
        'voltage_r' => 'decimal:2',
        'voltage_s' => 'decimal:2',
        'voltage_t' => 'decimal:2',
        'current_r' => 'decimal:2',
        'current_s' => 'decimal:2',
        'current_t' => 'decimal:2',
        'power_factor' => 'decimal:3',
        'frequency' => 'decimal:2',
        'read_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function powerMeter(): BelongsTo
    {
        return $this->belongsTo(PowerMeter::class);
    }

    /**
     * Mutu sinyal WiFi dari nilai dBm, memakai ambang yang lazim dipakai
     * perangkat lapangan. Angka dBm selalu negatif; makin mendekati nol makin
     * kuat.
     *
     * @return array{level:int, label:string, tone:string}  level 0–4
     */
    public function getSignalQualityAttribute(): array
    {
        $dbm = $this->signal_dbm;

        return match (true) {
            $dbm === null => ['level' => 0, 'label' => 'Tidak diketahui', 'tone' => 'unknown'],
            $dbm >= -55 => ['level' => 4, 'label' => 'Kuat', 'tone' => 'good'],
            $dbm >= -67 => ['level' => 3, 'label' => 'Baik', 'tone' => 'good'],
            $dbm >= -75 => ['level' => 2, 'label' => 'Cukup', 'tone' => 'fair'],
            $dbm >= -85 => ['level' => 1, 'label' => 'Lemah', 'tone' => 'weak'],
            default => ['level' => 1, 'label' => 'Sangat lemah', 'tone' => 'poor'],
        };
    }
}
