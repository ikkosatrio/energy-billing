<?php

namespace App\Services;

use App\Models\ActivityLog;
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
                'user_id' => auth()->id(),
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
