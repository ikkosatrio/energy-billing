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
        'payment_batch_id',
        'import_hash',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'receipt_issued_at' => 'datetime',
        'receipt_sent_at' => 'datetime',
        'receipt_paid_total' => 'decimal:2',
        'receipt_outstanding_after' => 'decimal:2',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function hasReceipt(): bool
    {
        return $this->receipt_no !== null;
    }

    public function receiptSent(): bool
    {
        return $this->receipt_sent_at !== null;
    }

    /**
     * Kuitansi ini menutup seluruh tagihan, atau baru sebagian.
     *
     * Memakai snapshot saat kuitansi terbit — bukan status invoice saat ini —
     * supaya dokumen yang sudah dikirim tidak berubah artinya ketika pelanggan
     * membayar sisanya di kemudian hari.
     */
    public function receiptSettlesInvoice(): bool
    {
        return $this->receipt_outstanding_after !== null
            && (float) $this->receipt_outstanding_after <= 0.5;
    }

    /**
     * Sidik jari satu baris berkas impor, dipakai unique index untuk menolak
     * berkas yang diunggah dua kali.
     *
     * Nomor referensi ikut dihitung karena satu pelanggan bisa sah membayar
     * invoice yang sama dua kali di hari yang sama dengan nominal sama
     * (mis. dua kali cicilan) — yang membedakan hanya nomor transaksinya.
     */
    public static function importHash(int $invoiceId, string $date, float $amount, ?string $reference): string
    {
        return hash('sha256', implode('|', [
            $invoiceId,
            $date,
            number_format($amount, 2, '.', ''),
            trim((string) $reference),
        ]));
    }
}
