<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TariffGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(TariffRate::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Tarif yang berlaku pada tanggal tertentu (default: hari ini).
     * Dipakai saat generate invoice untuk mengambil harga per kWh.
     */
    public function rateOn(?string $date = null): ?TariffRate
    {
        return $this->rates()->effectiveOn($date ?? now()->toDateString())->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
