<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembacaan mentah dari gateway. `stand_lwbp` / `stand_wbp` adalah angka
 * kumulatif meter, bukan pemakaian — pemakaian selalu berupa selisih dua
 * pembacaan.
 *
 * Keduanya register yang independen (akumulator tarif berbeda), jadi
 * sengaja tidak ada accessor total_stand — menjumlahkan LWBP+WBP jadi satu
 * "stand total" tidak berarti apa-apa secara fisik.
 */
class MeterReading extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'power_meter_id',
        'read_at',
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
        'source',
        'raw_payload',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
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
        'raw_payload' => 'array',
    ];

    public function powerMeter(): BelongsTo
    {
        return $this->belongsTo(PowerMeter::class);
    }

    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('read_at', [$from, $to]);
    }

    /**
     * Pembacaan pertama pada atau setelah $from — dipakai sebagai stand awal
     * periode saat generate invoice.
     */
    public function scopeFirstFrom(Builder $query, string $from): Builder
    {
        return $query->where('read_at', '>=', $from)->orderBy('read_at');
    }

    /**
     * Pembacaan terakhir pada atau sebelum $to — stand akhir periode.
     */
    public function scopeLastUntil(Builder $query, string $to): Builder
    {
        return $query->where('read_at', '<=', $to)->orderByDesc('read_at');
    }
}
