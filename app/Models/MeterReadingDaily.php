<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ringkasan harian hasil agregasi `meter_readings`. Chart bulanan dan tahunan
 * membaca dari sini, bukan dari pembacaan mentah.
 */
class MeterReadingDaily extends Model
{
    use HasFactory;

    /** Jumlah pembacaan ideal dalam sehari pada interval 1 menit. */
    public const EXPECTED_READINGS = 1440;

    protected $fillable = [
        'power_meter_id',
        'date',
        'stand_lwbp_start',
        'stand_lwbp_end',
        'stand_wbp_start',
        'stand_wbp_end',
        'kwh_lwbp',
        'kwh_wbp',
        'peak_kw',
        'peak_at',
        'reading_count',
    ];

    protected $casts = [
        'date' => 'date',
        'stand_lwbp_start' => 'decimal:2',
        'stand_lwbp_end' => 'decimal:2',
        'stand_wbp_start' => 'decimal:2',
        'stand_wbp_end' => 'decimal:2',
        'kwh_lwbp' => 'decimal:2',
        'kwh_wbp' => 'decimal:2',
        'peak_kw' => 'decimal:2',
        'peak_at' => 'datetime',
        'reading_count' => 'integer',
    ];

    public function powerMeter(): BelongsTo
    {
        return $this->belongsTo(PowerMeter::class);
    }

    public function getTotalKwhAttribute(): float
    {
        return (float) $this->kwh_lwbp + (float) $this->kwh_wbp;
    }

    /**
     * Menandai hari dengan data tidak lengkap, mis. gateway sempat offline.
     * Ambang 90% memberi toleransi untuk beberapa push yang gagal.
     */
    public function getIsIncompleteAttribute(): bool
    {
        return $this->reading_count < (self::EXPECTED_READINGS * 0.9);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
