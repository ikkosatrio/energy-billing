<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use Carbon\Carbon;

/**
 * Menyiapkan angka-angka untuk halaman Dashboard. Dipisah dari controller
 * supaya query-nya bisa dipakai ulang (mis. oleh report) dan mudah diuji.
 */
class DashboardService
{
    /**
     * Total pemakaian kWh bulan berjalan beserta perbandingannya terhadap
     * bulan lalu. Dibaca dari agregat harian, bukan pembacaan mentah.
     */
    public function monthlyUsage(?Carbon $month = null): array
    {
        $month ??= now()->startOfMonth();
        $previous = $month->copy()->subMonth();

        $current = $this->sumUsage($month);
        $before = $this->sumUsage($previous);

        // Bulan lalu nol berarti tidak ada pembanding — jangan tampilkan
        // kenaikan tak berhingga.
        $change = $before > 0 ? (($current - $before) / $before) * 100 : null;

        return [
            'total' => $current,
            'previous' => $before,
            'change_percent' => $change,
            'previous_label' => $previous->translatedFormat('F Y'),
        ];
    }

    private function sumUsage(Carbon $month): float
    {
        $rows = MeterReadingDaily::query()
            ->between($month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString())
            ->selectRaw('COALESCE(SUM(kwh_lwbp), 0) AS lwbp, COALESCE(SUM(kwh_wbp), 0) AS wbp')
            ->first();

        return (float) $rows->lwbp + (float) $rows->wbp;
    }

    /**
     * Nilai tagihan periode berjalan — invoice yang sudah terbit bulan ini.
     */
    public function currentBilling(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $total = (float) Invoice::whereBetween('issue_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        return [
            'total' => $total,
            'label' => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
        ];
    }

    /**
     * Jumlah meter online vs total meter yang tidak dinonaktifkan.
     */
    public function meterStatus(): array
    {
        $meters = PowerMeter::where('status', '!=', 'inactive')->get(['id', 'status', 'last_seen_at']);
        $online = $meters->filter->isOnline()->count();

        return [
            'total' => $meters->count(),
            'online' => $online,
            'offline' => $meters->count() - $online,
        ];
    }

    /**
     * Total tagihan yang belum lunas beserta jumlah invoice jatuh tempo.
     */
    public function outstanding(): array
    {
        $invoices = Invoice::unpaid()->get(['total_amount', 'paid_amount', 'due_date', 'status']);

        return [
            'amount' => $invoices->sum(fn ($i) => $i->outstanding),
            'count' => $invoices->count(),
            'overdue_count' => $invoices->where('status', 'overdue')->count(),
        ];
    }

    /**
     * Meter beserta pelanggan dan daya sesaat terakhirnya, untuk panel
     * "Status Meter" di dashboard.
     */
    public function meterList(int $limit = 6)
    {
        return PowerMeter::with(['customer:id,power_meter_id,name', 'latestReading'])
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function recentInvoices(int $limit = 5)
    {
        return Invoice::with('billingPeriod:id,code')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
