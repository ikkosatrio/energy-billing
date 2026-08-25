<?php

namespace App\Livewire\Portal;

use App\Models\PowerMeter;
use App\Services\Monitoring\UsageSummaryService;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;

/**
 * Monitoring real-time versi portal — kartu per meter, sama isinya dengan
 * halaman staf tapi hanya untuk meter milik pelanggan yang diakses.
 *
 * Filter jenis sambungan (1/3 phase) tidak dibawa ke sini: pelanggan portal
 * biasanya punya satu sampai beberapa meter, dan menyaringnya per jenis
 * sambungan bukan pertanyaan yang mereka miliki.
 */
class MonitoringPage extends PortalComponent
{
    protected string $permission = 'portal.monitoring.view';

    public const REFRESH_OPTIONS = [
        10 => '10s',
        30 => '30s',
        60 => '1m',
        300 => '5m',
    ];

    #[Session(key: 'portal.monitoring-refresh')]
    public int $refreshEvery = 30;

    public function updatedRefreshEvery(int $value): void
    {
        if ($value !== 0 && !array_key_exists($value, self::REFRESH_OPTIONS)) {
            $this->refreshEvery = 0;
        }
    }

    #[On('refresh-portal-monitoring')]
    public function refresh(): void
    {
        // wire:poll memanggil ini; render ulang sudah cukup.
    }

    public function render(UsageSummaryService $usageSummary)
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name,daya_kva,tariff_group_id', 'latestReading', 'deviceStatus'])
            ->whereIn('id', $this->meterIds())
            ->orderBy('name')
            ->get();

        return view('livewire.portal.monitoring-page', [
            'meters' => $meters,
            'cards' => $meters->map(fn (PowerMeter $meter) => $meter->statusBadge())->all(),
            'usage' => $usageSummary->forMeters($meters),
        ]);
    }
}
