<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CustomerUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat jejak audit. Sengaja tidak melempar exception: kegagalan menulis
 * log tidak boleh membatalkan aksi bisnis yang sedang berjalan.
 */
class ActivityLogger
{
    public static function log(
        string $action,
        ?Model $model = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        try {
            ActivityLog::create([
                ...self::actor(),
                'action' => $action,
                'model_type' => $model ? $model::class : null,
                'model_id' => $model?->getKey(),
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Pelaku aksi, dipetakan ke kolom yang benar sesuai guard-nya.
     *
     * `activity_logs.user_id` adalah foreign key ke tabel staf, jadi id akun
     * portal TIDAK boleh masuk ke sana — auth()->id() saja akan mengirim id
     * yang salah tabel begitu request datang dari portal.
     *
     * @return array{user_id:?int, customer_user_id:?int}
     */
    private static function actor(): array
    {
        if ($portal = auth()->guard(CustomerUser::GUARD)->user()) {
            return ['user_id' => null, 'customer_user_id' => $portal->getKey()];
        }

        return ['user_id' => auth()->guard('web')->id(), 'customer_user_id' => null];
    }

    /**
     * Mencatat perubahan sebuah model beserta nilai lama & barunya.
     * Panggil SEBELUM save() agar getOriginal() masih memuat nilai lama.
     */
    public static function logModelChange(string $action, Model $model, ?string $description = null): void
    {
        $changes = $model->getDirty();

        self::log(
            action: $action,
            model: $model,
            description: $description,
            oldValues: array_intersect_key($model->getOriginal(), $changes) ?: null,
            newValues: $changes ?: null,
        );
    }
}
