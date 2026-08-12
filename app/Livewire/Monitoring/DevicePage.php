<?php

namespace App\Livewire\Monitoring;

use App\Models\MeterReading;
use App\Models\PowerMeter;
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

    public function render()
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name', 'latestReading'])
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
            'todayCounts' => $this->todayReadingCounts($meters->pluck('id')->all()),
            'expected' => $this->expectedReadingsToday(),
        ]);
    }

    /**
     * Jumlah pembacaan yang masuk hari ini per meter — dibandingkan dengan
     * jumlah yang seharusnya untuk mendeteksi data bolong.
     *
     * @param  array<int>  $meterIds
     * @return array<int, int>
     */
    private function todayReadingCounts(array $meterIds): array
    {
        if (empty($meterIds)) {
            return [];
        }

        return MeterReading::query()
            ->whereIn('power_meter_id', $meterIds)
            ->between(now()->startOfDay()->toDateTimeString(), now()->toDateTimeString())
            ->selectRaw('power_meter_id, COUNT(*) AS jumlah')
            ->groupBy('power_meter_id')
            ->pluck('jumlah', 'power_meter_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Perkiraan jumlah pembacaan yang seharusnya sudah masuk sejak tengah
     * malam sampai sekarang, berdasarkan interval push yang dikonfigurasi.
     */
    private function expectedReadingsToday(): int
    {
        $interval = max(1, (int) setting('iot_push_interval_seconds', 60));

        return max(1, (int) floor(now()->diffInSeconds(now()->copy()->startOfDay()) / $interval));
    }
}
