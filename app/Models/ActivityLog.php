<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    // Tabel hanya punya created_at (log tidak pernah diubah).
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'customer_user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pelaku dari portal pelanggan; null bila barisnya dibuat staf. */
    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class);
    }

    /**
     * Nama pelaku, dari sisi mana pun ia masuk. Baris tanpa keduanya berasal
     * dari perintah terjadwal, yang memang tidak punya pelaku.
     */
    public function getActorNameAttribute(): string
    {
        return $this->user?->name
            ?? ($this->customerUser ? $this->customerUser->name.' (portal)' : 'Sistem');
    }
}
