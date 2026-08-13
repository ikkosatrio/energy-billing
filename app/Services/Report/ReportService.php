<?php

namespace App\Services\Report;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Rekap pemakaian kWh per pelanggan dalam satu rentang tanggal.
     *
     * Sumbernya agregat harian, bukan invoice — sehingga rekap tetap bisa
     * dibuat untuk periode yang belum ditagihkan.
     */
    public function usage(Carbon $from, Carbon $to, ?int $customerId = null): Collection
    {
        $customers = Customer::query()
            ->with(['powerMeter:id,code,name', 'tariffGroup:id,code'])
            ->whereNotNull('power_meter_id')
            ->when($customerId, fn ($q) => $q->where('id', $customerId))
            ->orderBy('name')
            ->get();

        $stats = MeterReadingDaily::query()
            ->whereIn('power_meter_id', $customers->pluck('power_meter_id'))
            ->between($from->toDateString(), $to->toDateString())
            ->selectRaw('power_meter_id,
                         SUM(kwh_lwbp) AS lwbp,
                         SUM(kwh_wbp) AS wbp,
                         MAX(peak_kw) AS peak_kw,
                         COUNT(*) AS hari')
            ->groupBy('power_meter_id')
            ->get()
            ->keyBy('power_meter_id');

        // Tagihan diambil dari invoice yang periodenya berada di dalam rentang.
        $billed = Invoice::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->whereDate('period_start', '>=', $from->toDateString())
            ->whereDate('period_end', '<=', $to->toDateString())
            ->selectRaw('customer_id, SUM(total_amount) AS total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        return $customers->map(function ($customer) use ($stats, $billed) {
            // $stat null bila meter belum pernah punya agregat harian —
            // pelanggan baru, atau gateway-nya belum pernah mengirim data.
            $stat = $stats->get($customer->power_meter_id);
            $lwbp = (float) ($stat?->lwbp ?? 0);
            $wbp = (float) ($stat?->wbp ?? 0);

            return [
                'customer' => $customer->name,
                'code' => $customer->code,
                'meter' => $customer->powerMeter?->code,
                'tariff_group' => $customer->tariffGroup?->code,
                'lwbp' => $lwbp,
                'wbp' => $wbp,
                'total_kwh' => $lwbp + $wbp,
                'peak_kw' => $stat?->peak_kw !== null ? (float) $stat->peak_kw : null,
                'days' => (int) ($stat?->hari ?? 0),
                'billed' => (float) ($billed[$customer->id] ?? 0),
            ];
        });
    }

    /**
     * Rekap tagihan dan penerimaan per pelanggan.
     *
     * Invoice yang dibatalkan tidak dihitung karena bukan tagihan yang berlaku.
     */
    public function billing(Carbon $from, Carbon $to, ?int $customerId = null): Collection
    {
        return Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->orderBy('customer_name')
            ->orderByDesc('issue_date')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_no' => $invoice->invoice_no,
                'customer' => $invoice->customer_name,
                'period' => $invoice->period_start->translatedFormat('M Y'),
                'issue_date' => $invoice->issue_date,
                'due_date' => $invoice->due_date,
                'total_kwh' => $invoice->total_kwh,
                'total' => (float) $invoice->total_amount,
                'paid' => (float) $invoice->paid_amount,
                'outstanding' => $invoice->outstanding,
                'status' => $invoice->status,
            ]);
    }

    /** Batas baris export data mentah, menjaga memori worker Excel. */
    public const RAW_EXPORT_LIMIT = 50_000;

    /**
     * Query pembacaan mentah satu meter dalam rentang tanggal.
     *
     * Selalu dibatasi satu meter: tabelnya bisa berisi jutaan baris, dan
     * membaca lintas meter tanpa batas akan menghabiskan memori.
     */
    public function rawReadingsQuery(int $meterId, Carbon $from, Carbon $to)
    {
        return MeterReading::query()
            ->where('power_meter_id', $meterId)
            ->between($from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString())
            ->orderBy('read_at');
    }

    /**
     * Menandai anomali pada deretan pembacaan.
     *
     * Dua hal yang dicari:
     *   stand_mundur — stand kumulatif turun; meter di-reset atau berputar
     *                  kembali ke nol, dan pemakaiannya jadi tidak terhitung
     *   jeda         — selisih waktu jauh di atas interval push, artinya
     *                  gateway sempat mati atau kehilangan jaringan
     *
     * $previous adalah pembacaan tepat sebelum baris pertama, supaya baris
     * pembuka halaman ikut terperiksa dan bukan selalu dianggap normal.
     *
     * @param  iterable<MeterReading>  $readings
     * @return array<int, array<string, mixed>>
     */
    public function flagAnomalies(iterable $readings, ?MeterReading $previous = null): array
    {
        $interval = max(1, (int) setting('iot_push_interval_seconds', 60));

        // Ambang jeda: 3× interval, tapi minimal 5 menit.
        //
        // Kelipatan saja terlalu sensitif pada interval pendek — dengan push
        // tiap 60 detik, satu-dua push yang telat karena jaringan sudah cukup
        // menandai hampir semua baris sebagai bermasalah, dan tabel penuh
        // sorotan merah justru menyembunyikan gangguan yang sungguhan.
        $gapThreshold = max($interval * 3, 300);

        $rows = [];

        foreach ($readings as $reading) {
            $standDropped = $previous
                && ((float) $reading->stand_lwbp < (float) $previous->stand_lwbp
                    || (float) $reading->stand_wbp < (float) $previous->stand_wbp);

            $gapSeconds = $previous ? $previous->read_at->diffInSeconds($reading->read_at) : null;
            $hasGap = $gapSeconds !== null && $gapSeconds > $gapThreshold;

            $rows[] = [
                'reading' => $reading,
                'delta_lwbp' => $previous ? (float) $reading->stand_lwbp - (float) $previous->stand_lwbp : null,
                'delta_wbp' => $previous ? (float) $reading->stand_wbp - (float) $previous->stand_wbp : null,
                'gap_seconds' => $gapSeconds,
                'stand_dropped' => $standDropped,
                'has_gap' => $hasGap,
                'is_anomaly' => $standDropped || $hasGap,
            ];

            $previous = $reading;
        }

        return $rows;
    }

    /**
     * Data mentah siap export. Dibatasi RAW_EXPORT_LIMIT baris.
     */
    public function rawReadings(int $meterId, Carbon $from, Carbon $to): Collection
    {
        $readings = $this->rawReadingsQuery($meterId, $from, $to)
            ->limit(self::RAW_EXPORT_LIMIT)
            ->get();

        return collect($this->flagAnomalies($readings))->map(fn ($row) => [
            'read_at' => $row['reading']->read_at,
            'stand_lwbp' => (float) $row['reading']->stand_lwbp,
            'stand_wbp' => (float) $row['reading']->stand_wbp,
            'delta_lwbp' => $row['delta_lwbp'],
            'delta_wbp' => $row['delta_wbp'],
            'active_power_kw' => $row['reading']->active_power_kw,
            'voltage_r' => $row['reading']->voltage_r,
            'current_r' => $row['reading']->current_r,
            'power_factor' => $row['reading']->power_factor,
            'frequency' => $row['reading']->frequency,
            'source' => $row['reading']->source,
            'catatan' => $row['stand_dropped'] ? 'Stand mundur' : ($row['has_gap'] ? 'Jeda data' : ''),
        ]);
    }

    /**
     * Baris total untuk kaki tabel laporan.
     */
    public function totals(Collection $rows, array $columns): array
    {
        return collect($columns)
            ->mapWithKeys(fn ($column) => [$column => $rows->sum($column)])
            ->all();
    }
}
