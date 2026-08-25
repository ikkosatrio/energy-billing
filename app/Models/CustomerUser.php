<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Akun login portal pelanggan (guard 'customer').
 *
 * Otorisasinya bertumpu pada DUA hal yang tidak saling menggantikan:
 *
 *   permission  — boleh melakukan apa (`portal.invoice.view`, dst), lewat
 *                 role portal yang mekanismenya sama dengan role staf.
 *   akses       — data pelanggan siapa yang boleh dilihat, lewat pivot
 *                 `customer_user_access`.
 *
 * Keduanya harus lolos. Akun dengan `portal.invoice.view` tetap tidak bisa
 * membuka invoice pelanggan yang tidak ada di daftar aksesnya — itulah
 * pemisah antara "fitur mana yang aktif" dan "data milik siapa".
 */
class CustomerUser extends Authenticatable
{
    use HasFactory, SoftDeletes;

    /** Nama guard-nya, dipakai juga sebagai nilai kolom `roles.guard`. */
    public const GUARD = 'customer';

    /**
     * Daftar akses di-cache per instance: hampir setiap query portal
     * memanggilnya, dan satu halaman bisa punya beberapa komponen Livewire
     * yang masing-masing menanyakannya ulang dalam satu request.
     *
     * @var array<int, int>|null
     */
    private ?array $cachedCustomerIds = null;

    /** @var array<int, int>|null */
    private ?array $cachedMeterIds = null;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'phone',
        'is_active',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /** Role portal — baris `roles` dengan guard 'customer'. */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Pelanggan yang boleh dilihat akun ini. */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_user_access')
            ->orderBy('customers.name');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cek satu permission portal berdasarkan slug (mis. 'portal.invoice.view').
     *
     * Berbeda dari User::hasPermission(), sengaja TIDAK ADA jalur super admin:
     * portal tidak boleh punya akun yang lolos segalanya. Akun tanpa role
     * berarti tanpa permission — default yang aman.
     */
    public function hasPermission(string $slug): bool
    {
        $role = $this->relationLoaded('role') ? $this->role : $this->loadMissing('role')->role;

        if (!$role) {
            return false;
        }

        return $role->loadMissing('permissions')
            ->permissions
            ->contains('slug', $slug);
    }

    /**
     * Lolos bila punya SALAH SATU permission pada daftar. Daftar kosong
     * berarti terbuka untuk semua akun portal — dipakai config/portal-menu.php
     * untuk item tanpa pembatasan.
     */
    public function hasAnyPermission(array $slugs): bool
    {
        if (empty($slugs)) {
            return true;
        }

        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, int> */
    public function accessibleCustomerIds(): array
    {
        return $this->cachedCustomerIds ??= $this->customers()
            ->pluck('customers.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * ID power meter yang boleh diakses, diturunkan dari daftar pelanggan —
     * satu pelanggan memakai tepat satu meter, jadi tidak perlu dicatat
     * terpisah.
     *
     * Pelanggan yang meternya belum terpasang dibuang di query, supaya
     * `whereIn` tidak pernah menerima null.
     *
     * @return array<int, int>
     */
    public function accessibleMeterIds(): array
    {
        return $this->cachedMeterIds ??= $this->customers()
            ->whereNotNull('power_meter_id')
            ->pluck('customers.power_meter_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canAccessCustomer(?int $customerId): bool
    {
        return $customerId !== null
            && in_array($customerId, $this->accessibleCustomerIds(), true);
    }

    public function canAccessMeter(?int $meterId): bool
    {
        return $meterId !== null
            && in_array($meterId, $this->accessibleMeterIds(), true);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');

        return mb_strtoupper($initials ?: mb_substr($this->username, 0, 2));
    }
}
