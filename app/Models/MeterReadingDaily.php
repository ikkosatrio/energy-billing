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
        'reset_count',
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
        'reset_count' => 'integer',
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
     *
     * Jumlah ideal dihitung dari interval push YANG DIKONFIGURASI, bukan
     * diasumsikan 1 menit — sebelumnya konstan 1.440 di sini membuat SETIAP
     * hari selalu ditandai tidak lengkap begitu gateway memakai interval
     * lain (mis. 30 menit → wajar cuma 48 baris sehari, jauh di bawah 1.440).
     */
    public function getIsIncompleteAttribute(): bool
    {
        $interval = max(1, (int) setting('iot_push_interval_seconds', 60));
        $expected = max(1, (int) floor(86400 / $interval));

        return $this->reading_count < ($expected * 0.9);
    }

    /**
     * Hari yang mengandung reset meter — angkanya masih dihitung dari
     * penjumlahan selisih, tapi tetap perlu diperiksa manusia karena sisa
     * pemakaian sebelum titik reset tidak bisa dipastikan.
     */
    public function getHasResetAttribute(): bool
    {
        return $this->reset_count > 0;
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
