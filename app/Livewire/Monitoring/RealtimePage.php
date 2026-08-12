<?php

namespace App\Livewire\Monitoring;

use App\Models\MeterReading;
use App\Models\PowerMeter;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Kartu pembacaan terakhir tiap meter, disegarkan berkala lewat wire:poll.
 */
class RealtimePage extends Component
{
    /** Ambang "beban tinggi" dalam persen dari daya tersambung pelanggan. */
    private const HIGH_LOAD_PERCENT = 80;

    public bool $autoRefresh = true;

    #[On('refresh-realtime')]
    public function refresh(): void
    {
        // wire:poll memanggil ini; render ulang sudah cukup untuk mengambil
        // pembacaan terbaru.
    }

    public function render()
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name,daya_kva', 'latestReading'])
            ->where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get();

        return view('livewire.monitoring.realtime-page', [
            'meters' => $meters,
            'cards' => $meters->map(fn ($meter) => $this->buildCard($meter)),
            'todayUsage' => $this->todayUsage($meters->pluck('id')->all()),
            'pollInterval' => max(15, (int) setting('iot_push_interval_seconds', 60)).'s',
        ]);
    }

    /**
     * Menentukan label status kartu. "Beban tinggi" hanya bisa dinilai bila
     * pelanggan mencantumkan daya tersambung.
     *
     * @return array{status:string, badge:string}
     */
    private function buildCard(PowerMeter $meter): array
    {
        if (!$meter->isOnline()) {
            return ['status' => $meter->status === 'maintenance' ? 'Maintenance' : 'Offline',
                'badge' => $meter->status === 'maintenance' ? 'badge-warning' : 'badge-danger'];
        }

        $kw = (float) ($meter->latestReading?->active_power_kw ?? 0);
        $kva = (float) ($meter->customer?->daya_kva ?? 0);

        if ($kva > 0 && $kw >= $kva * (self::HIGH_LOAD_PERCENT / 100)) {
            return ['status' => 'Beban Tinggi', 'badge' => 'badge-warning'];
        }

        return ['status' => 'Normal', 'badge' => 'badge-success'];
    }

    /**
     * Pemakaian kWh hari ini per meter, dihitung dari selisih stand pertama
     * dan terakhir hari ini — bukan dari agregat harian, yang baru terisi
     * setelah job agregasi berjalan.
     *
     * @param  array<int>  $meterIds
     * @return array<int, float>
     */
    private function todayUsage(array $meterIds): array
    {
        if (empty($meterIds)) {
            return [];
        }

        $rows = MeterReading::query()
            ->whereIn('power_meter_id', $meterIds)
            ->between(now()->startOfDay()->toDateTimeString(), now()->toDateTimeString())
            ->selectRaw('power_meter_id,
                         MAX(stand_lwbp) - MIN(stand_lwbp) AS lwbp,
                         MAX(stand_wbp) - MIN(stand_wbp) AS wbp')
            ->groupBy('power_meter_id')
            ->get();

        return $rows->mapWithKeys(fn ($row) => [
            $row->power_meter_id => (float) $row->lwbp + (float) $row->wbp,
        ])->all();
    }
}
