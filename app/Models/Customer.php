<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pelanggan = penyewa gudang. Satu pelanggan memakai tepat satu power meter.
 */
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'pic_name',
        'npwp',
        'power_meter_id',
        'tariff_group_id',
        'daya_kva',
        'biaya_beban_mode',
        'biaya_beban',
        'billing_day',
        'contract_start',
        'contract_end',
        'status',
        'notes',
    ];

    protected $casts = [
        'daya_kva' => 'decimal:2',
        'biaya_beban' => 'decimal:2',
        'billing_day' => 'integer',
        'contract_start' => 'date',
        'contract_end' => 'date',
    ];

    public function powerMeter(): BelongsTo
    {
        return $this->belongsTo(PowerMeter::class);
    }

    public function tariffGroup(): BelongsTo
    {
        return $this->belongsTo(TariffGroup::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Tanggal generate invoice yang berlaku: pakai override milik pelanggan
     * bila diisi, kalau tidak ikut default global di setting sistem.
     */
    public function effectiveBillingDay(): int
    {
        return $this->billing_day ?? (int) setting('billing_cut_off_day', 1);
    }

    /**
     * Biaya beban untuk sebuah periode. Mode 'per_kva' mengalikan daya
     * tersambung dengan tarif beban golongan yang berlaku saat itu.
     */
    public function biayaBebanFor(?TariffRate $rate): float
    {
        if ($this->biaya_beban_mode === 'per_kva') {
            return (float) $this->daya_kva * (float) ($rate?->rate_beban_per_kva ?? 0);
        }

        return (float) $this->biaya_beban;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Pelanggan yang siap ditagih: aktif dan sudah punya meter serta golongan
     * tarif. Tanpa dua data itu invoice tidak bisa dihitung.
     */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('power_meter_id')
            ->whereNotNull('tariff_group_id');
    }
}
