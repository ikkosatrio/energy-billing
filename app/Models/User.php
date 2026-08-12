<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role_id',
        'phone',
        'avatar_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Cek satu permission berdasarkan slug (mis. 'invoice.generate').
     * Super admin selalu lolos tanpa perlu baris di permission_role.
     */
    public function hasPermission(string $slug): bool
    {
        $role = $this->relationLoaded('role') ? $this->role : $this->loadMissing('role')->role;

        if (!$role) {
            return false;
        }

        if ($role->isSuperAdmin()) {
            return true;
        }

        return $role->loadMissing('permissions')
            ->permissions
            ->contains('slug', $slug);
    }

    /**
     * Lolos bila user punya SALAH SATU permission pada daftar.
     * Daftar kosong berarti terbuka untuk semua user yang sudah login —
     * dipakai config/menu.php untuk item tanpa pembatasan.
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

    public function isSuperAdmin(): bool
    {
        return (bool) $this->role?->isSuperAdmin();
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');

        return mb_strtoupper($initials ?: mb_substr($this->username, 0, 2));
    }
}
