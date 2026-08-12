<?php

namespace App\Livewire\Tariff;

use App\Models\MeterTariffSchedule;
use App\Models\PowerMeter;
use App\Services\ActivityLogger;
use App\Services\Tariff\ScheduleValidator;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Konfigurasi jadwal WBP/LWBP per meter.
 *
 * Jadwal ini DISIMPAN DI APLIKASI saja — tidak dikirim ke perangkat. Meter
 * sudah mengirim register LWBP dan WBP terpisah, sehingga jadwal di sini
 * berfungsi sebagai referensi: menampilkan tarif aktif saat ini, mewarnai
 * chart, dan menjadi acuan saat mencocokkan dengan setelan di perangkat.
 */
class TariffSchedulePage extends Component
{
    public ?int $meterId = null;

    /** @var array<int, array{start_time:string, tariff_type:string}> */
    public array $periods = [];

    /** @var array<int, string> */
    public array $issues = [];

    public function mount(): void
    {
        $this->meterId = PowerMeter::orderBy('name')->value('id');
        $this->loadSchedule();
    }

    public function updatedMeterId(): void
    {
        $this->loadSchedule();
    }

    public function loadSchedule(): void
    {
        $this->issues = [];

        if (!$this->meterId) {
            $this->periods = [];

            return;
        }

        $saved = MeterTariffSchedule::where('power_meter_id', $this->meterId)
            ->orderBy('sequence')
            ->get(['start_time', 'tariff_type']);

        $this->periods = $saved->isEmpty()
            ? $this->defaultPeriods()
            : $saved->map(fn ($row) => [
                'start_time' => substr($row->start_time, 0, 5),
                'tariff_type' => $row->tariff_type,
            ])->all();
    }

    public function addPeriod(): void
    {
        if (count($this->periods) >= MeterTariffSchedule::MAX_PERIODS) {
            $this->dispatch('toast', type: 'warning', message: 'Maksimal '.MeterTariffSchedule::MAX_PERIODS.' periode.');

            return;
        }

        $this->periods[] = ['start_time' => '12:00', 'tariff_type' => 'LWBP'];
    }

    public function removePeriod(int $index): void
    {
        unset($this->periods[$index]);
        $this->periods = array_values($this->periods);
    }

    public function save(ScheduleValidator $validator): void
    {
        $this->authorize('tariff.update');

        if (!$this->meterId) {
            return;
        }

        $this->issues = $validator->validate($this->periods);

        if ($this->issues) {
            $this->dispatch('toast', type: 'error', message: 'Jadwal belum valid. Periksa daftar kesalahan di bawah.');

            return;
        }

        $rows = $validator->normalize($this->periods);

        DB::transaction(function () use ($rows) {
            // Ditulis ulang seluruhnya: sequence dan start_time punya unique
            // index, sehingga update parsial mudah bentrok di tengah jalan.
            MeterTariffSchedule::where('power_meter_id', $this->meterId)->delete();

            foreach ($rows as $row) {
                MeterTariffSchedule::create($row + ['power_meter_id' => $this->meterId]);
            }
        });

        $meter = PowerMeter::find($this->meterId);
        ActivityLogger::log('updated', $meter, "Ubah jadwal WBP/LWBP meter {$meter->code}", newValues: $rows);

        $this->loadSchedule();
        $this->dispatch('toast', type: 'success', message: 'Jadwal tersimpan.');
    }

    /**
     * Pola awal saat meter belum punya jadwal — mengikuti contoh pada desain.
     *
     * @return array<int, array{start_time:string, tariff_type:string}>
     */
    private function defaultPeriods(): array
    {
        return [
            ['start_time' => '00:00', 'tariff_type' => 'LWBP'],
            ['start_time' => '17:00', 'tariff_type' => 'WBP'],
            ['start_time' => '22:00', 'tariff_type' => 'LWBP'],
        ];
    }

    public function render(ScheduleValidator $validator)
    {
        // Pratinjau memakai data form yang sedang diedit, bukan yang tersimpan,
        // sehingga pita 24 jam dan ringkasan langsung ikut berubah.
        $valid = empty($validator->validate($this->periods));
        $rows = $valid ? $validator->normalize($this->periods) : [];
        $totals = $rows ? $validator->totals($rows) : ['LWBP' => 0, 'WBP' => 0];

        return view('livewire.tariff.tariff-schedule-page', [
            'meters' => PowerMeter::orderBy('name')->get(['id', 'code', 'name']),
            'meter' => $this->meterId ? PowerMeter::with('customer:id,power_meter_id,name')->find($this->meterId) : null,
            'rows' => $rows,
            'totals' => $totals,
            'isValid' => $valid,
        ]);
    }
}
