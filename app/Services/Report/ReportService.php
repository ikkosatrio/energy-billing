<?php

namespace App\Services\Report;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
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
                         MAX(peak_kw) AS peak_kw')
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

    /**
     * Transaksi pembayaran dalam satu rentang TANGGAL BAYAR — beda dari
     * billing() yang berbasis tanggal terbit. Satu baris per pembayaran,
     * bukan per invoice, karena satu invoice bisa dicicil berkali-kali dan
     * tiap cicilan perlu terlihat sebagai baris tersendiri untuk ditelusuri.
     */
    public function payments(
        Carbon $from,
        Carbon $to,
        ?int $customerId = null,
        ?string $method = null,
        bool $partialOnly = false,
    ): Collection {
        return InvoicePayment::query()
            ->with([
                'invoice:id,invoice_no,customer_id,customer_name,total_amount,paid_amount,status',
                'recordedBy:id,name',
                'batch:id,type',
            ])
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->when($customerId, fn ($q) => $q->whereHas('invoice', fn ($sub) => $sub->where('customer_id', $customerId)))
            ->when($method, fn ($q) => $q->where('method', $method))
            ->when($partialOnly, fn ($q) => $q->whereHas('invoice', fn ($sub) => $sub->where('status', 'partial')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InvoicePayment $payment) => [
                'payment_date' => $payment->payment_date,
                'invoice_no' => $payment->invoice?->invoice_no,
                'customer' => $payment->invoice?->customer_name,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'recorded_by' => $payment->recordedBy?->name,
                // Batch mengungkap asal-usul pembayaran — pembayaran individual
                // yang tercatat lewat pelunasan massal atau impor berkas rawan
                // terlewat saat ditelusuri satu-satu.
                'source' => match ($payment->batch?->type) {
                    'import' => 'Impor',
                    'bulk' => 'Massal',
                    default => 'Manual',
                },
                'invoice_status' => $payment->invoice?->status,
                'invoice_outstanding' => $payment->invoice?->outstanding,
            ]);
    }

    /**
     * Ringkasan tunggakan HARI INI — sengaja tidak terikat rentang tanggal
     * bayar pada payments(): pertanyaannya beda ("berapa yang masih harus
     * ditagih sekarang" vs "berapa yang masuk pada rentang ini"), jadi
     * jawabannya juga tidak boleh ikut kepotong filter tanggal itu.
     *
     * @return array{
     *     partial: array{count:int, amount:float},
     *     aging: array<string, array{label:string, count:int, amount:float}>
     * }
     */
    public function paymentTracking(): array
    {
        $unpaid = Invoice::unpaid()->get(['id', 'due_date', 'total_amount', 'paid_amount', 'status']);
        $partial = $unpaid->where('status', 'partial');

        $buckets = [
            'current' => ['label' => 'Belum Jatuh Tempo', 'count' => 0, 'amount' => 0.0],
            'd1_30' => ['label' => '1–30 Hari', 'count' => 0, 'amount' => 0.0],
            'd31_60' => ['label' => '31–60 Hari', 'count' => 0, 'amount' => 0.0],
            'd60_plus' => ['label' => '> 60 Hari', 'count' => 0, 'amount' => 0.0],
        ];

        foreach ($unpaid as $invoice) {
            $overdueDays = $invoice->due_date && $invoice->due_date->isPast()
                ? $invoice->due_date->diffInDays(now())
                : 0;

            $bucket = match (true) {
                $overdueDays <= 0 => 'current',
                $overdueDays <= 30 => 'd1_30',
                $overdueDays <= 60 => 'd31_60',
                default => 'd60_plus',
            };

            $buckets[$bucket]['count']++;
            $buckets[$bucket]['amount'] += $invoice->outstanding;
        }

        return [
            'partial' => ['count' => $partial->count(), 'amount' => (float) $partial->sum('outstanding')],
            'aging' => $buckets,
        ];
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

        // Meter 1 phase tidak punya jalur S dan T, jadi kolomnya tidak ikut
        // diekspor — sama seperti tampilan di layar.
        $lines = PowerMeter::find($meterId)?->isSinglePhase() ? ['r'] : ['r', 's', 't'];

        return collect($this->flagAnomalies($readings))->map(function ($row) use ($lines) {
            $reading = $row['reading'];

            $data = [
                'read_at' => $reading->read_at,
                'stand_lwbp' => (float) $reading->stand_lwbp,
                'stand_wbp' => (float) $reading->stand_wbp,
                'delta_lwbp' => $row['delta_lwbp'],
                'delta_wbp' => $row['delta_wbp'],
                'active_power_kw' => $reading->active_power_kw,
            ];

            foreach ($lines as $line) {
                $data['voltage_'.$line] = $reading->{'voltage_'.$line};
            }

            foreach ($lines as $line) {
                $data['current_'.$line] = $reading->{'current_'.$line};
            }

            return $data + [
                'power_factor' => $reading->power_factor,
                'frequency' => $reading->frequency,
                'source' => $reading->source,
                'catatan' => $row['stand_dropped'] ? 'Stand mundur' : ($row['has_gap'] ? 'Jeda data' : ''),
            ];
        });
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
