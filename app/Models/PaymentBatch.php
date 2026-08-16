<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu operasi pembayaran massal, sebagai kesatuan yang bisa dibatalkan.
 *
 * @see \App\Services\Billing\BulkPaymentService
 */
class PaymentBatch extends Model
{
    protected $fillable = [
        'type',
        'source',
        'payment_count',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_count' => 'integer',
        'total_amount' => 'decimal:2',
        'reverted_at' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }

    public function typeLabel(): string
    {
        return $this->type === 'import' ? 'Impor berkas' : 'Tandai lunas';
    }
}
