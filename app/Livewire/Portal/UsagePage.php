<?php

namespace App\Livewire\Portal;

use App\Models\Customer;
use App\Models\MeterReadingDaily;
use Illuminate\Support\Carbon;

/**
 * Rekap pemakaian kWh per pelanggan pada satu rentang tanggal.
 *
 * Tidak memakai ReportService::usage() milik staf walau bentuknya mirip:
 * di sana kolom "Ditagihkan" diambil dari invoice, dan halaman ini harus
 * tetap terbuka untuk akun "Pantau Saja" yang justru tidak boleh melihat
 * nominal tagihan. Memasang ulang penyaring kolom di atas service itu lebih
 * mudah salah daripada menghitung rekap kWh-nya sendiri di sini.
 */
class UsagePage extends PortalComponent
{
    protected string $permission = 'portal.usage.view';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render()
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        // Rentang terbalik akan menghasilkan tabel kosong tanpa penjelasan,
        // jadi ditandai supaya blade bisa mengatakannya.
        $rangeValid = $from->lessThanOrEqualTo($to);

        $rows = $rangeValid ? $this->rows($from, $to) : collect();

        return view('livewire.portal.usage-page', [
            'rows' => $rows,
            'rangeValid' => $rangeValid,
            'totals' => [
                'lwbp' => (float) $rows->sum('lwbp'),
                'wbp' => (float) $rows->sum('wbp'),
                'total_kwh' => (float) $rows->sum('total_kwh'),
            ],
        ]);
    }

    /**
     * Satu baris per pelanggan yang boleh diakses — termasuk yang belum ada
     * datanya, supaya lokasi yang gateway-nya belum mengirim apa pun terlihat
     * sebagai nol, bukan hilang dari daftar tanpa jejak.
     */
    private function rows(Carbon $from, Carbon $to)
    {
        $customers = Customer::query()
            ->with(['powerMeter:id,code,name'])
            ->whereIn('id', $this->customerIds())
            ->orderBy('name')
            ->get();

        $stats = MeterReadingDaily::query()
            ->whereIn('power_meter_id', $customers->pluck('power_meter_id')->filter())
            ->between($from->toDateString(), $to->toDateString())
            ->selectRaw('power_meter_id,
                         SUM(kwh_lwbp) AS lwbp,
                         SUM(kwh_wbp) AS wbp,
                         MAX(peak_kw) AS peak_kw')
            ->groupBy('power_meter_id')
            ->get()
            ->keyBy('power_meter_id');

        return $customers->map(function (Customer $customer) use ($stats) {
            $stat = $stats->get($customer->power_meter_id);
            $lwbp = (float) ($stat?->lwbp ?? 0);
            $wbp = (float) ($stat?->wbp ?? 0);

            return [
                'customer' => $customer->name,
                'meter' => $customer->powerMeter?->code ?? '—',
                'lwbp' => $lwbp,
                'wbp' => $wbp,
                'total_kwh' => $lwbp + $wbp,
                'peak_kw' => $stat?->peak_kw !== null ? (float) $stat->peak_kw : null,
            ];
        });
    }
}
