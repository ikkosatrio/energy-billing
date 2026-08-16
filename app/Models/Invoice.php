<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Seluruh angka pada invoice sudah di-snapshot saat generate. Jangan
 * mengambil tarif atau data pelanggan lewat relasi untuk keperluan cetak —
 * pakai kolom snapshot di baris ini agar invoice lama tidak ikut berubah.
 */
class Invoice extends Model
{
    use HasFactory;

    /**
     * Satu-satunya sumber label status — dipakai baik oleh komponen badge
     * <x-invoice-status> di layar maupun export laporan (Excel/PDF tidak
     * bisa memanggil komponen Blade), supaya labelnya tidak pernah bisa
     * berbeda antara yang dilihat dan yang diunduh.
     */
    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'issued' => 'Belum Bayar',
        'partial' => 'Bayar Sebagian',
        'paid' => 'Lunas',
        'overdue' => 'Jatuh Tempo',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'invoice_no',
        'billing_period_id',
        'customer_id',
        'power_meter_id',
        'tariff_rate_id',
        'customer_name',
        'customer_address',
        'customer_npwp',
        'meter_code',
        'tariff_group_code',
        'period_start',
        'period_end',
        'stand_lwbp_start',
        'stand_lwbp_end',
        'kwh_lwbp',
        'rate_lwbp',
        'amount_lwbp',
        'stand_wbp_start',
        'stand_wbp_end',
        'kwh_wbp',
        'rate_wbp',
        'amount_wbp',
        'biaya_beban_mode',
        'daya_kva',
        'rate_beban_per_kva',
        'biaya_beban',
        'biaya_admin',
        'subtotal',
        'ppj_percent',
        'ppj_amount',
        'ppn_percent',
        'ppn_amount',
        'rounding',
        'adjustment',
        'total_amount',
        'issue_date',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'stand_lwbp_start' => 'decimal:2',
        'stand_lwbp_end' => 'decimal:2',
        'kwh_lwbp' => 'decimal:2',
        'rate_lwbp' => 'decimal:2',
        'amount_lwbp' => 'decimal:2',
        'stand_wbp_start' => 'decimal:2',
        'stand_wbp_end' => 'decimal:2',
        'kwh_wbp' => 'decimal:2',
        'rate_wbp' => 'decimal:2',
        'amount_wbp' => 'decimal:2',
        'daya_kva' => 'decimal:2',
        'rate_beban_per_kva' => 'decimal:2',
        'biaya_beban' => 'decimal:2',
        'biaya_admin' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'ppj_percent' => 'decimal:3',
        'ppj_amount' => 'decimal:2',
        'ppn_percent' => 'decimal:3',
        'ppn_amount' => 'decimal:2',
        'rounding' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function powerMeter(): BelongsTo
    {
        return $this->belongsTo(PowerMeter::class);
    }

    public function tariffRate(): BelongsTo
    {
        return $this->belongsTo(TariffRate::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Status yang masih boleh dibatalkan.
     *
     * `partial` dan `paid` sengaja tidak masuk: uangnya sudah diterima, dan
     * membatalkan tagihan yang sudah dibayar akan membuat pembayaran itu
     * menggantung tanpa tagihan induk. Koreksinya lewat refund atau nota
     * kredit, bukan pembatalan. Pemeriksaan pembayaran tetap dilakukan
     * terpisah karena `issued` pun bisa sudah punya pembayaran sebagian
     * yang belum sempat mengubah status.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'issued', 'overdue'], true);
    }

    public function getTotalKwhAttribute(): float
    {
        return (float) $this->kwh_lwbp + (float) $this->kwh_wbp;
    }

    public function getOutstandingAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    /**
     * Hitung ulang paid_amount dari seluruh pembayaran lalu sesuaikan status.
     * Dipanggil setiap kali baris pembayaran ditambah, diubah, atau dihapus.
     */
    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->total_amount;
        $latestPayment = $this->payments()->orderByDesc('payment_date')->first();

        // Selisih di bawah 1 rupiah dianggap lunas agar pembulatan tidak
        // menyisakan tagihan recehan yang tidak mungkin dibayar.
        $isPaid = $paid >= $total - 0.5;

        $status = match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $isPaid => 'paid',
            $paid > 0 => 'partial',
            $this->due_date && $this->due_date->isPast() => 'overdue',
            default => $this->status === 'draft' ? 'draft' : 'issued',
        };

        $this->forceFill([
            'paid_amount' => $paid,
            'paid_at' => $isPaid ? ($latestPayment?->payment_date ?? now()) : null,
            'status' => $status,
        ])->save();
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partial', 'overdue']);
    }

    public function scopeForPeriod(Builder $query, int $periodId): Builder
    {
        return $query->where('billing_period_id', $periodId);
    }
}
