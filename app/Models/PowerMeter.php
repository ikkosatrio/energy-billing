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

    /** Ambang "beban tinggi" dalam persen dari daya tersambung pelanggan. */
    public const HIGH_LOAD_PERCENT = 80;

    protected $fillable = [
        'code',
        'name',
        'serial_no',
        'brand',
        'model',
        'phase',
        'location',
        'ct_ratio',
        'multiplier',
        'stand_max',
        'status',
        'installed_at',
        'notes',
    ];

    protected $casts = [
        'multiplier' => 'decimal:4',
        'stand_max' => 'decimal:2',
        'installed_at' => 'date',
        'last_seen_at' => 'datetime',
    ];

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Kondisi terakhir perangkat — ditimpa tiap kiriman status, tanpa riwayat.
     *
     * Sengaja TIDAK dinamai `status`: kolom `status` pada tabel ini sudah
     * dipakai untuk status administratif (active/inactive/maintenance), dan
     * Eloquent mendahulukan atribut daripada relasi — relasinya akan tertutup
     * diam-diam tanpa pesan kesalahan.
     */
    public function deviceStatus(): HasOne
    {
        return $this->hasOne(PowerMeterStatus::class);
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

    /**
     * Titik putar register dalam satuan yang sama dengan kolom stand di
     * `meter_readings` — yaitu setelah dikali CT, karena pembacaan sudah
     * dikalikan saat disimpan.
     */
    public function getEffectiveStandMaxAttribute(): ?float
    {
        return $this->stand_max === null
            ? null
            : (float) $this->stand_max * (float) $this->multiplier;
    }

    /** Meter 1 phase hanya punya satu jalur tegangan dan arus. */
    public function isSinglePhase(): bool
    {
        return $this->phase === '1';
    }

    public function getPhaseLabelAttribute(): string
    {
        return $this->isSinglePhase() ? '1 Phase' : '3 Phase';
    }

    public function isOnline(): bool
    {
        return $this->connection_status === 'online';
    }

    /**
     * Label status kartu monitoring: Normal, Beban Tinggi, Offline, atau
     * Maintenance. Dipakai bersama oleh Real-time Monitoring dan ringkasan
     * perangkat di Dashboard supaya kriterianya selalu sama persis di kedua
     * tempat — perlu relasi `customer`, `deviceStatus`, dan `latestReading`
     * sudah dimuat lebih dulu oleh pemanggil.
     *
     * @return array{status:string, badge:string}
     */
    public function statusBadge(): array
    {
        if (!$this->isOnline()) {
            return $this->status === 'maintenance'
                ? ['status' => 'Maintenance', 'badge' => 'badge-warning']
                : ['status' => 'Offline', 'badge' => 'badge-danger'];
        }

        $kw = (float) ($this->deviceStatus?->active_power_kw ?? $this->latestReading?->active_power_kw ?? 0);
        $kva = (float) ($this->customer?->daya_kva ?? 0);

        if ($kva > 0 && $kw >= $kva * (self::HIGH_LOAD_PERCENT / 100)) {
            return ['status' => 'Beban Tinggi', 'badge' => 'badge-warning'];
        }

        return ['status' => 'Normal', 'badge' => 'badge-success'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
