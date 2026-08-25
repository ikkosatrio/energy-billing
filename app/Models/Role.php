<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Role dipakai dua audiens yang dipisahkan kolom `guard`: staf ('web') dan
 * portal pelanggan ('customer'). Tabelnya sengaja satu supaya halaman "Role
 * & Hak Akses" dan `Gate::before` tetap satu jalur — yang dipisah hanya
 * daftar permission yang boleh dipilih untuk masing-masing.
 *
 * `slug` tetap unique lintas guard, jadi role portal diberi awalan `portal-`
 * agar tidak pernah bertabrakan dengan role staf.
 */
class Role extends Model
{
    use HasFactory;

    /**
     * Role dengan akses penuh — pengecekan permission selalu lolos.
     * Hanya berlaku untuk guard 'web'; portal tidak punya padanannya.
     */
    public const SUPER_ADMIN = 'super-admin';

    protected $fillable = [
        'name',
        'slug',
        'guard',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected $attributes = [
        'guard' => 'web',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customerUsers(): HasMany
    {
        return $this->hasMany(CustomerUser::class);
    }

    /**
     * Super admin hanya bermakna pada guard staf. Tanpa pemeriksaan guard di
     * sini, role portal yang kebetulan diberi slug 'super-admin' akan lolos
     * segalanya — dan slug unique saja tidak cukup menjamin itu tidak terjadi
     * lewat impor atau seeder pihak lain.
     */
    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN && $this->guard === 'web';
    }

    public function isPortal(): bool
    {
        return $this->guard === CustomerUser::GUARD;
    }

    public function scopeForGuard(Builder $query, string $guard): Builder
    {
        return $query->where('guard', $guard);
    }

    /** Role staf — dipakai halaman User Management & Role. */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('guard', 'web');
    }

    /** Role portal pelanggan. */
    public function scopePortal(Builder $query): Builder
    {
        return $query->where('guard', CustomerUser::GUARD);
    }
}
