<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Kolom `guard` memisahkan permission staf ('web') dari permission portal
 * pelanggan ('customer'), sehingga saat menyusun role portal yang muncul
 * sebagai pilihan hanya permission portal — bukan seluruh daftar permission
 * aplikasi termasuk yang berbahaya seperti `user.delete`.
 */
class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'guard',
        'group',
        'description',
    ];

    protected $attributes = [
        'guard' => 'web',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function scopeForGuard(Builder $query, string $guard): Builder
    {
        return $query->where('guard', $guard);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('guard', 'web');
    }

    public function scopePortal(Builder $query): Builder
    {
        return $query->where('guard', CustomerUser::GUARD);
    }
}
