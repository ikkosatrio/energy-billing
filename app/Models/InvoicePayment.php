<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_date',
        'amount',
        'method',
        'reference_no',
        'proof_path',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Status invoice selalu mengikuti total pembayarannya, jadi disegarkan
     * otomatis setiap baris pembayaran berubah — tidak perlu diingat manual
     * di setiap controller.
     */
    protected static function booted(): void
    {
        $refresh = fn (InvoicePayment $payment) => $payment->invoice?->refreshPaymentStatus();

        static::saved($refresh);
        static::deleted($refresh);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
