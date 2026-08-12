<?php

namespace App\Services\Report;

use App\Models\Customer;
use App\Models\Invoice;
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
