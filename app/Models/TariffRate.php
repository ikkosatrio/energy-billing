<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_group_id',
        'rate_lwbp',
        'rate_wbp',
        'rate_beban_per_kva',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected $casts = [
        'rate_lwbp' => 'decimal:2',
        'rate_wbp' => 'decimal:2',
        'rate_beban_per_kva' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function tariffGroup(): BelongsTo
    {
        return $this->belongsTo(TariffGroup::class);
    }

    /**
     * Tarif yang masa berlakunya mencakup tanggal $date.
     * effective_to NULL berarti masih berlaku sampai sekarang.
     */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from');
    }
}
