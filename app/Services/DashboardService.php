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
     * Nilai tagihan periode yang sedang ditagihkan.
     *
     * Dasarnya PERIODE PEMAKAIAN, bukan tanggal terbit invoice.
     *
     * Tagihan listrik selalu menagih bulan yang sudah selesai — invoice yang
     * terbit awal September berisi pemakaian Agustus. Dengan tanggal terbit
     * sebagai dasar, kartu ini berlabel "01 Sep – 30 Sep" tapi isinya uang
     * atas pemakaian Agustus, dan setiap awal bulan sebelum generate
     * dijalankan angkanya Rp 0 — terbaca seperti tidak ada tagihan sama
     * sekali, padahal tagihan yang belum dibayar masih berjalan.
     *
     * Periode yang dipakai sama dengan yang dipakai perintah generate
     * (now()->subMonth()), sehingga angka di dashboard dan periode yang
     * ditagihkan operator selalu bicara tentang bulan yang sama.
     *
     * Invoice draft tidak dihitung. Draft belum ditagihkan ke siapa pun dan
     * angkanya masih berubah setiap kali digenerate ulang — memasukkannya
     * membuat kartu ini bergerak tanpa ada transaksi apa pun, dan membuatnya
     * berbeda aturan dengan kartu Belum Dibayar di sebelahnya.
     */
    public function currentBilling(): array
    {
        $period = now()->subMonth();
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $invoices = Invoice::whereBetween('period_start', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled', 'draft']);

        return [
            'total' => (float) $invoices->sum('total_amount'),
            'label' => $start->translatedFormat('d M').' – '.$end->translatedFormat('d M Y'),
            // Nol karena belum ditagihkan berbeda artinya dari nol karena
            // tidak ada pemakaian. Tanpa penanda ini keduanya terlihat sama
            // di layar, dan periode yang terlewat digenerate tidak terlihat
            // oleh siapa pun sampai pelanggan menanyakan tagihannya.
            'issued_count' => $invoices->count(),
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
}
