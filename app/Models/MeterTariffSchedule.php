<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterTariffSchedule extends Model
{
    use HasFactory;

    /** Batas yang divalidasi di halaman Jadwal WBP/LWBP. */
    public const MAX_PERIODS = 12;

    /** Jam mulai/selesai harus kelipatan menit ini. */
    public const SLOT_MINUTES = 15;

    protected $fillable = [
        'power_meter_id',
        'sequence',
        'start_time',
        'end_time',
        'tariff_type',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function powerMeter(): BelongsTo
    {
        return $this->belongsTo(PowerMeter::class);
    }

    /**
     * Durasi periode dalam menit. Baris terakhir menyimpan end_time 00:00
     * (mewakili 24:00), jadi durasinya dihitung memutari tengah malam.
     */
    public function getDurationMinutesAttribute(): int
    {
        $start = $this->minutesOf($this->start_time);
        $end = $this->minutesOf($this->end_time);

        return $end > $start ? $end - $start : (1440 - $start) + $end;
    }

    private function minutesOf(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}
