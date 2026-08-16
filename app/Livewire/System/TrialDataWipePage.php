<?php

namespace App\Livewire\System;

use App\Models\PowerMeter;
use App\Services\ActivityLogger;
use App\Services\Monitoring\TrialDataWipeService;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * Hapus data mentah + agregat harian per meter pada rentang tanggal bebas —
 * untuk membersihkan data uji coba/dummy, bukan alur retensi rutin (lihat
 * ReadingReportPage untuk itu). Route sudah dijaga permission
 * reading.wipe_trial; authorize() di wipeNow() dipertahankan sebagai lapis
 * kedua karena action Livewire dipanggil lewat endpoint sendiri, bukan lewat
 * route halaman.
 */
class TrialDataWipePage extends Component
{
    private TrialDataWipeService $wiper;

    public function boot(TrialDataWipeService $wiper): void
    {
        $this->wiper = $wiper;
    }

    public ?int $meterId = null;

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->meterId = PowerMeter::orderBy('code')->value('id');
        $this->from = now()->subDays(7)->toDateString();
        $this->to = now()->toDateString();
    }

    public function render()
    {
        $meters = PowerMeter::orderBy('code')->get(['id', 'code', 'name', 'location']);
        $meter = $this->meterId ? $meters->firstWhere('id', $this->meterId) : null;

        $preview = ['readings' => 0, 'dailies' => 0];
        $overlaps = collect();
        $rangeValid = $meter && $this->from && $this->to && $this->from <= $this->to;

        if ($rangeValid) {
            $from = Carbon::parse($this->from);
            $to = Carbon::parse($this->to);

            $preview = $this->wiper->preview($meter, $from, $to);
            $overlaps = $this->wiper->overlappingInvoices($meter, $from, $to);
        }

        return view('livewire.system.trial-data-wipe-page', [
            'meters' => $meters,
            'meter' => $meter,
            'preview' => $preview,
            'overlaps' => $overlaps,
            'rangeValid' => $rangeValid,
        ]);
    }

    public function wipeNow(): void
    {
        $this->authorize('reading.wipe_trial');

        abort_unless($this->meterId && $this->from && $this->to && $this->from <= $this->to, 422);

        $meter = PowerMeter::findOrFail($this->meterId);
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $result = $this->wiper->wipe($meter, $from, $to);

        if ($result['readings'] === 0 && $result['dailies'] === 0) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada data pada rentang ini untuk meter tersebut.');

            return;
        }

        ActivityLogger::log(
            'wiped',
            $meter,
            "Hapus data uji coba {$meter->code}: {$result['readings']} pembacaan mentah, "
                ."{$result['dailies']} agregat harian ({$from->toDateString()} s/d {$to->toDateString()})",
        );

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Data {$meter->code} pada {$from->translatedFormat('d M Y')}–{$to->translatedFormat('d M Y')} "
                ."dihapus: {$result['readings']} pembacaan, {$result['dailies']} agregat harian.",
        );
    }
}
