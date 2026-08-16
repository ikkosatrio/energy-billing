<?php

namespace App\Services\Monitoring;

use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hapus pembacaan mentah DAN agregat harian untuk satu meter pada rentang
 * tanggal bebas — dipakai membersihkan data uji coba/dummy sebelum sistem
 * dipakai sungguhan.
 *
 * Beda mendasar dari DataRetentionService::purge(): di sana agregat harian
 * (meter_readings_daily) sengaja TIDAK PERNAH ikut terhapus supaya chart dan
 * laporan periode lama tetap utuh. Di sini agregatnya justru ikut dihapus,
 * karena data uji coba tidak boleh menyisakan jejak di chart bulanan/tahunan.
 * Karena lebih berbahaya, operasi ini sengaja terpisah dari alur retensi dan
 * tidak dipakai jadwal otomatis maupun tombol retensi biasa.
 */
class TrialDataWipeService
{
    /** @return array{readings:int, dailies:int} */
    public function preview(PowerMeter $meter, Carbon $from, Carbon $to): array
    {
        return [
            'readings' => MeterReading::where('power_meter_id', $meter->id)
                ->whereBetween('read_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                ->count(),
            'dailies' => MeterReadingDaily::where('power_meter_id', $meter->id)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->count(),
        ];
    }

    /**
     * Invoice meter ini yang periodenya beririsan dengan rentang wipe.
     * Dipakai memperingatkan operator, BUKAN memblokir — kalau meternya
     * memang cuma dipakai untuk uji coba, invoice yang overlap biasanya
     * juga data uji coba dan boleh diabaikan.
     */
    public function overlappingInvoices(PowerMeter $meter, Carbon $from, Carbon $to): Collection
    {
        return Invoice::where('power_meter_id', $meter->id)
            ->where('period_start', '<=', $to)
            ->where('period_end', '>=', $from)
            ->orderBy('period_start')
            ->get(['id', 'invoice_no', 'period_start', 'period_end', 'status']);
    }

    /** @return array{readings:int, dailies:int} */
    public function wipe(PowerMeter $meter, Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        // Hapus bertahap: tabel pembacaan mentah bisa berisi puluhan ribu
        // baris per meter per bulan.
        $readingsDeleted = 0;
        do {
            $deleted = MeterReading::where('power_meter_id', $meter->id)
                ->whereBetween('read_at', [$start, $end])
                ->limit(5000)
                ->delete();
            $readingsDeleted += $deleted;
        } while ($deleted > 0);

        $dailiesDeleted = MeterReadingDaily::where('power_meter_id', $meter->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        return ['readings' => $readingsDeleted, 'dailies' => $dailiesDeleted];
    }
}
