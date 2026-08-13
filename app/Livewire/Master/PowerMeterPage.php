<?php

namespace App\Livewire\Master;

use App\Models\PowerMeter;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class PowerMeterPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $editingId = null;

    public bool $showForm = false;

    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'form.code' => ['required', 'string', 'max:50', Rule::unique('power_meters', 'code')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.serial_no' => ['nullable', 'string', 'max:100'],
            'form.brand' => ['nullable', 'string', 'max:100'],
            'form.model' => ['nullable', 'string', 'max:100'],
            'form.location' => ['nullable', 'string', 'max:255'],
            'form.ct_ratio' => ['nullable', 'string', 'max:50'],
            'form.multiplier' => ['required', 'numeric', 'min:0.0001'],
            'form.stand_max' => ['nullable', 'numeric', 'min:1'],
            'form.status' => ['required', 'in:active,inactive,maintenance'],
            'form.installed_at' => ['nullable', 'date'],
            'form.notes' => ['nullable', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.code' => 'kode meter',
            'form.name' => 'nama meter',
            'form.multiplier' => 'pengali',
            'form.stand_max' => 'angka maksimum register',
        ];
    }

    public function create(): void
    {
        $this->authorize('meter.create');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('meter.update');

        $meter = PowerMeter::findOrFail($id);

        $this->editingId = $meter->id;
        $this->form = [
            'code' => $meter->code,
            'name' => $meter->name,
            'serial_no' => $meter->serial_no,
            'brand' => $meter->brand,
            'model' => $meter->model,
            'location' => $meter->location,
            'ct_ratio' => $meter->ct_ratio,
            'multiplier' => $meter->multiplier,
            'stand_max' => $meter->stand_max,
            'status' => $meter->status,
            'installed_at' => $meter->installed_at?->toDateString(),
            'notes' => $meter->notes,
        ];

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'meter.update' : 'meter.create');

        $data = $this->validate()['form'];

        if ($this->editingId) {
            $meter = PowerMeter::findOrFail($this->editingId);
            $meter->fill($data);
            ActivityLogger::logModelChange('updated', $meter, "Ubah power meter {$meter->code}");
            $meter->save();
        } else {
            $meter = PowerMeter::create($data);
            ActivityLogger::log('created', $meter, "Tambah power meter {$meter->code}");
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: "Data power meter tersimpan. ID meter: {$meter->id}");
    }

    public function delete(int $id): void
    {
        $this->authorize('meter.delete');

        $meter = PowerMeter::findOrFail($id);

        if ($meter->customer()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Meter masih terhubung ke pelanggan. Lepaskan dulu dari data pelanggan.');

            return;
        }

        if ($meter->readings()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Meter sudah punya riwayat pembacaan. Ubah statusnya jadi nonaktif saja.');

            return;
        }

        ActivityLogger::log('deleted', $meter, "Hapus power meter {$meter->code}");
        $meter->delete();

        $this->dispatch('toast', type: 'success', message: 'Power meter dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'serial_no' => '',
            'brand' => '',
            'model' => '',
            'location' => '',
            'ct_ratio' => '',
            'multiplier' => 1,
            'stand_max' => null,
            'status' => 'active',
            'installed_at' => null,
            'notes' => '',
        ];
        $this->resetErrorBag();
    }

    public function render()
    {
        $meters = PowerMeter::query()
            ->with(['customer:id,power_meter_id,name', 'latestReading'])
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('serial_no', 'like', "%{$this->search}%")
                    ->orWhere('location', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.master.power-meter-page', [
            'meters' => $meters,
            'ingestUrl' => url('/api/v1/readings'),
            'docsUrl' => url('/api/documentation'),
        ]);
    }
}
