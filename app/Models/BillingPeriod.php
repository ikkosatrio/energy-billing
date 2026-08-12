<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'period_start',
        'period_end',
        'cut_off_date',
        'status',
        'generated_at',
        'generated_by',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'cut_off_date' => 'date',
        'generated_at' => 'datetime',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Periode yang sudah ditutup tidak boleh digenerate ulang atau diubah.
     */
    public function isLocked(): bool
    {
        return $this->status === 'closed';
    }

    public function getLabelAttribute(): string
    {
        return $this->period_start->translatedFormat('F Y');
    }
}
