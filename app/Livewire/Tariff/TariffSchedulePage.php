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

    /** Modal "Duplikat dari meter lain". */
    public bool $showCopy = false;

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

    /**
     * Menyalin jadwal meter lain ke form yang sedang dibuka.
     *
     * Hasilnya sengaja BELUM disimpan: pengguna masih bisa memeriksa pita 24
     * jam dan mengubah jamnya dulu. Menyalin langsung ke database akan
     * menimpa jadwal yang ada tanpa sempat dilihat.
     */
    public function copyFrom(int $sourceMeterId): void
    {
        $this->authorize('tariff.update');

        if (!$this->meterId || $sourceMeterId === $this->meterId) {
            return;
        }

        $source = MeterTariffSchedule::where('power_meter_id', $sourceMeterId)
            ->orderBy('sequence')
            ->get(['start_time', 'tariff_type']);

        if ($source->isEmpty()) {
            $this->dispatch('toast', type: 'error', message: 'Meter itu belum punya jadwal tersimpan.');

            return;
        }

        $this->periods = $source->map(fn ($row) => [
            'start_time' => substr($row->start_time, 0, 5),
            'tariff_type' => $row->tariff_type,
        ])->all();

        $this->issues = [];
        $this->showCopy = false;

        $name = PowerMeter::where('id', $sourceMeterId)->value('code');
        $this->dispatch('toast', type: 'success', message: "Jadwal {$name} disalin. Periksa dulu, lalu klik Simpan Jadwal.");
    }

    /**
     * Meter lain yang sudah punya jadwal tersimpan, beserta ringkasannya.
     *
     * Yang belum punya jadwal tidak ditawarkan — menyalin dari meter kosong
     * tidak menghasilkan apa-apa selain kebingungan.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function copySources()
    {
        if (!$this->meterId) {
            return collect();
        }

        $schedules = MeterTariffSchedule::where('power_meter_id', '!=', $this->meterId)
            ->orderBy('sequence')
            ->get(['power_meter_id', 'start_time', 'tariff_type'])
            ->groupBy('power_meter_id');

        if ($schedules->isEmpty()) {
            return collect();
        }

        return PowerMeter::whereIn('id', $schedules->keys())
            ->with('customer:id,power_meter_id,name')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (PowerMeter $meter) => [
                'id' => $meter->id,
                'code' => $meter->code,
                'name' => $meter->name,
                'customer' => $meter->customer?->name,
                'periods' => $schedules[$meter->id]->map(fn ($row) => [
                    'start_time' => substr($row->start_time, 0, 5),
                    'tariff_type' => $row->tariff_type,
                ])->all(),
            ]);
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
            // Hanya disusun saat modalnya dibuka — daftar ini menarik seluruh
            // jadwal meter lain, dan halaman ini re-render tiap ketikan jam.
            'copySources' => $this->showCopy ? $this->copySources() : collect(),
        ]);
    }
}
