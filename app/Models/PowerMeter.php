<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PowerMeter extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Meter dianggap offline bila tidak ada pembacaan masuk selama ini.
     * Gateway push tiap menit, jadi 5 menit sudah cukup longgar untuk
     * mentoleransi keterlambatan jaringan tanpa telat mendeteksi mati.
     */
    public const OFFLINE_AFTER_MINUTES = 5;

    protected $fillable = [
        'code',
        'name',
        'serial_no',
        'brand',
        'model',
        'location',
        'ct_ratio',
        'multiplier',
        'status',
        'installed_at',
        'notes',
    ];

    protected $casts = [
        'multiplier' => 'decimal:4',
        'installed_at' => 'date',
        'last_seen_at' => 'datetime',
    ];

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function dailyReadings(): HasMany
    {
        return $this->hasMany(MeterReadingDaily::class);
    }

    public function tariffSchedules(): HasMany
    {
        return $this->hasMany(MeterTariffSchedule::class)->orderBy('sequence');
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(MeterReading::class)->latestOfMany('read_at');
    }

    /**
     * Status koneksi turunan: 'online' | 'offline' | 'maintenance'.
     * Berbeda dari kolom `status` yang menyatakan status administratif.
     */
    public function getConnectionStatusAttribute(): string
    {
        if ($this->status === 'maintenance') {
            return 'maintenance';
        }

        if (!$this->last_seen_at) {
            return 'offline';
        }

        return $this->last_seen_at->gt(now()->subMinutes(self::OFFLINE_AFTER_MINUTES))
            ? 'online'
            : 'offline';
    }

    public function isOnline(): bool
    {
        return $this->connection_status === 'online';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
