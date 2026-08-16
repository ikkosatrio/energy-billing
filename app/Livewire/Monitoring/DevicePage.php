<?php

namespace App\Livewire\Monitoring;

use App\Models\PowerMeter;
use App\Services\Monitoring\UsageSummaryService;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pemantauan kesehatan perangkat: siapa online, kapan terakhir mengirim, dan
 * seberapa lengkap datanya hari ini. Berbeda dari halaman Power Meter Device
 * di Master Data yang fokus ke pengelolaan datanya.
 */
class DevicePage extends Component
{
    use WithPagination;

    public string $connectionFilter = '';

    public function updatedConnectionFilter(): void
    {
        $this->resetPage();
    }

    public function render(UsageSummaryService $usageSummary)
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name', 'latestReading', 'deviceStatus'])
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get();

        // Difilter setelah query karena status koneksi diturunkan dari
        // last_seen_at, bukan kolom yang bisa di-WHERE langsung.
        if ($this->connectionFilter) {
            $meters = $meters->filter(fn ($m) => $m->connection_status === $this->connectionFilter)->values();
        }

        return view('livewire.monitoring.device-page', [
            'meters' => $meters,
            'todayUsage' => $usageSummary->liveToday($meters->pluck('id')->all()),
        ]);
    }
}
