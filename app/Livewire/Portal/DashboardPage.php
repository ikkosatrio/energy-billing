<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use App\Models\PowerMeter;
use App\Services\Monitoring\UsageSummaryService;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;

/**
 * Dashboard portal: kondisi meter sekarang, pemakaian hari ini & bulan ini,
 * lalu ringkasan tagihan yang belum lunas.
 *
 * Angka rupiah pemakaian sengaja tidak ditampilkan di sini walau tersedia
 * dari UsageSummaryService: itu perkiraan biaya energi saja (tanpa biaya
 * beban, admin, PPJ, PPN), dan berdampingan dengan kartu tagihan di halaman
 * yang sama, dua angka berbeda itu akan terbaca sebagai selisih yang salah.
 * Nominal yang sah hanya datang dari invoice.
 */
class DashboardPage extends PortalComponent
{
    protected string $permission = '';

    /** Sama dengan halaman staf — satu paradigma kontrol di seluruh aplikasi. */
    public const REFRESH_OPTIONS = [
        30 => '30s',
        60 => '1m',
        300 => '5m',
        600 => '10m',
    ];

    #[Session(key: 'portal.dashboard-refresh')]
    public int $refreshEvery = 60;

    public function updatedRefreshEvery(int $value): void
    {
        if ($value !== 0 && !array_key_exists($value, self::REFRESH_OPTIONS)) {
            $this->refreshEvery = 0;
        }
    }

    #[On('refresh-portal-dashboard')]
    public function refresh(): void
    {
        // wire:poll memanggil ini; render ulang sudah cukup.
    }

    public function render(UsageSummaryService $usageSummary)
    {
        $meterIds = $this->meterIds();

        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name,daya_kva,tariff_group_id', 'latestReading', 'deviceStatus'])
            ->whereIn('id', $meterIds)
            ->orderBy('name')
            ->get();

        $usage = $usageSummary->forMeters($meters);

        return view('livewire.portal.dashboard-page', [
            'meters' => $meters,
            'cards' => $meters->map(fn (PowerMeter $meter) => $meter->statusBadge())->all(),
            'usage' => $usage,
            'totalHariIni' => collect($usage)->sum(fn ($row) => $row['today']['kwh']),
            'totalBulanIni' => collect($usage)->sum(fn ($row) => $row['month']['kwh']),
            'offlineCount' => $meters->filter(fn (PowerMeter $meter) => !$meter->isOnline())->count(),
            'tagihan' => $this->tagihan(),
        ]);
    }

    /**
     * Ringkasan tagihan milik pelanggan yang diakses akun ini.
     *
     * Invoice draft tidak dihitung: belum resmi ditagihkan, jadi menampilkannya
     * ke pelanggan berarti memberi angka yang masih bisa berubah. Yang
     * dibatalkan juga tidak, karena bukan tagihan yang berlaku.
     *
     * @return array{outstanding:float, unpaid_count:int, overdue_count:int}
     */
    private function tagihan(): array
    {
        // Akun tanpa permission invoice tidak perlu angka tagihan sama sekali —
        // dan blade-nya memang tidak merendernya.
        if (!$this->portalUser()->hasPermission('portal.invoice.view')) {
            return ['outstanding' => 0.0, 'unpaid_count' => 0, 'overdue_count' => 0];
        }

        $unpaid = Invoice::query()
            ->whereIn('customer_id', $this->customerIds())
            ->unpaid()
            ->get(['total_amount', 'paid_amount', 'status']);

        return [
            'outstanding' => (float) $unpaid->sum(fn (Invoice $invoice) => $invoice->outstanding),
            'unpaid_count' => $unpaid->count(),
            'overdue_count' => $unpaid->where('status', 'overdue')->count(),
        ];
    }
}
