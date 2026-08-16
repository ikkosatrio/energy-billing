<?php

namespace App\Services\Monitoring;

use App\Models\MeterReading;
use App\Models\PowerMeter;
use Illuminate\Support\Carbon;

/**
 * Retensi & penghapusan pembacaan mentah (`meter_readings`).
 *
 * Satu tempat dipakai baik oleh jadwal mingguan (readings:prune, seluruh
 * meter sekaligus) maupun penghapusan manual per meter dari halaman Data
 * Meter Mentah — supaya keduanya selalu memakai cutoff dan cara hapus
 * bertahap yang sama persis, dan tidak ada jalur yang diam-diam menghapus
 * data yang masih dalam masa retensi.
 *
 * Agregat harian (`meter_readings_daily`) TIDAK PERNAH ikut dihapus di sini —
 * itu yang menjaga chart bulanan dan laporan periode lama tetap utuh
 * walau pembacaan mentahnya sudah dibuang.
 */
class DataRetentionService
{
    public function retentionMonths(): int
    {
        return (int) setting('iot_retention_months', 24);
    }

    /** Null berarti retensi dimatikan (< 1 bulan) — tidak ada yang boleh dihapus. */
    public function cutoff(?int $months = null): ?Carbon
    {
        $months ??= $this->retentionMonths();

        return $months >= 1 ? now()->subMonths($months) : null;
    }

    /**
     * Rentang data mentah yang BENAR-BENAR tersimpan untuk satu meter — beda
     * dari ringkasan "Rentang Data" di laporan, yang cuma menggambarkan hasil
     * filter tanggal yang sedang dipilih, bukan batas data yang sesungguhnya.
     *
     * @return array{count:int, first_at:?Carbon, last_at:?Carbon}
     */
    public function rangeFor(PowerMeter $meter): array
    {
        $stats = MeterReading::where('power_meter_id', $meter->id)
            ->selectRaw('COUNT(*) AS jumlah, MIN(read_at) AS pertama, MAX(read_at) AS terakhir')
            ->first();

        return [
            'count' => (int) ($stats->jumlah ?? 0),
            'first_at' => $stats->pertama ? Carbon::parse($stats->pertama) : null,
            'last_at' => $stats->terakhir ? Carbon::parse($stats->terakhir) : null,
        ];
    }

    /** Berapa baris akan terhapus untuk satu meter pada cutoff retensi saat ini. */
    public function wouldPurgeCount(PowerMeter $meter): int
    {
        $cutoff = $this->cutoff();

        return $cutoff
            ? MeterReading::where('power_meter_id', $meter->id)->where('read_at', '<', $cutoff)->count()
            : 0;
    }

    /**
     * Hapus bertahap (batch 5000 baris) supaya tidak mengunci tabel yang bisa
     * berisi jutaan baris terlalu lama. $meterId null berarti seluruh meter
     * sekaligus — dipakai jadwal mingguan; diisi berarti satu meter saja —
     * dipakai hapus manual.
     */
    public function purge(?int $meterId = null, ?int $months = null): int
    {
        $cutoff = $this->cutoff($months);

        if (!$cutoff) {
            return 0;
        }

        $total = 0;

        do {
            $deleted = MeterReading::query()
                ->when($meterId, fn ($q) => $q->where('power_meter_id', $meterId))
                ->where('read_at', '<', $cutoff)
                ->limit(5000)
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }
}
