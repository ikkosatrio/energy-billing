<?php

namespace App\Livewire\Master;

use App\Models\Customer;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\TariffGroup;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerPage extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    /** ID pelanggan yang sedang diedit; null berarti membuat baru. */
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
            'form.code' => ['required', 'string', 'max:50', Rule::unique('customers', 'code')->ignore($this->editingId)],
            'form.name' => ['required', 'string', 'max:255'],
            'form.address' => ['nullable', 'string'],
            'form.phone' => ['nullable', 'string', 'max:50'],
            // 'email:filter' memakai FILTER_VALIDATE_EMAIL yang menolak CRLF.
            // Aturan 'email' bawaan Laravel 10 masih meloloskannya, dan alamat
            // ini dipakai sebagai tujuan pengiriman invoice — lihat catatan
            // keamanan di README.
            'form.email' => ['nullable', 'email:filter', 'max:255'],
            'form.pic_name' => ['nullable', 'string', 'max:255'],
            'form.npwp' => ['nullable', 'string', 'max:50'],
            // Satu meter hanya boleh dipakai satu pelanggan.
            'form.power_meter_id' => ['nullable', 'integer', Rule::unique('customers', 'power_meter_id')->ignore($this->editingId)],
            'form.tariff_group_id' => ['nullable', 'integer', 'exists:tariff_groups,id'],
            'form.daya_kva' => ['nullable', 'numeric', 'min:0'],
            'form.biaya_beban_mode' => ['required', 'in:flat,per_kva'],
            'form.biaya_beban' => ['nullable', 'numeric', 'min:0'],
            'form.billing_day' => ['nullable', 'integer', 'between:1,28'],
            'form.contract_start' => ['nullable', 'date'],
            'form.contract_end' => ['nullable', 'date', 'after_or_equal:form.contract_start'],
            'form.status' => ['required', 'in:active,inactive'],
            'form.notes' => ['nullable', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.code' => 'kode',
            'form.name' => 'nama pelanggan',
            'form.power_meter_id' => 'power meter',
            'form.tariff_group_id' => 'golongan tarif',
            'form.daya_kva' => 'daya (kVA)',
            'form.biaya_beban' => 'biaya beban',
            'form.billing_day' => 'tanggal tagih',
            'form.contract_end' => 'akhir kontrak',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.power_meter_id.unique' => 'Power meter ini sudah dipakai pelanggan lain.',
            // Tanggal 29–31 tidak ada di setiap bulan, jadi dibatasi 28 agar
            // jadwal generate invoice selalu jatuh di tanggal yang sama.
            'form.billing_day.between' => 'Tanggal tagih harus antara 1 sampai 28.',
        ];
    }

    public function create(): void
    {
        $this->authorize('customer.create');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('customer.update');

        $customer = Customer::findOrFail($id);

        $this->editingId = $customer->id;
        $this->form = [
            'code' => $customer->code,
            'name' => $customer->name,
            'address' => $customer->address,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'pic_name' => $customer->pic_name,
            'npwp' => $customer->npwp,
            'power_meter_id' => $customer->power_meter_id,
            'tariff_group_id' => $customer->tariff_group_id,
            'daya_kva' => $customer->daya_kva,
            'biaya_beban_mode' => $customer->biaya_beban_mode,
            'biaya_beban' => $customer->biaya_beban,
            'billing_day' => $customer->billing_day,
            'contract_start' => $customer->contract_start?->toDateString(),
            'contract_end' => $customer->contract_end?->toDateString(),
            'status' => $customer->status,
            'notes' => $customer->notes,
        ];

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'customer.update' : 'customer.create');

        $data = $this->validate()['form'];

        // Field yang tidak relevan dengan mode terpilih dinolkan, supaya nilai
        // sisa dari mode sebelumnya tidak ikut terhitung saat generate invoice.
        if ($data['biaya_beban_mode'] === 'per_kva') {
            $data['biaya_beban'] = 0;
        }

        $data['daya_kva'] ??= 0;
        $data['biaya_beban'] ??= 0;

        if ($this->editingId) {
            $customer = Customer::findOrFail($this->editingId);
            $customer->fill($data);
            ActivityLogger::logModelChange('updated', $customer, "Ubah pelanggan {$customer->name}");
            $customer->save();
        } else {
            $customer = Customer::create($data);
            ActivityLogger::log('created', $customer, "Tambah pelanggan {$customer->name}");
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Data pelanggan tersimpan.');
    }

    public function delete(int $id): void
    {
        $this->authorize('customer.delete');

        $customer = Customer::findOrFail($id);

        // Invoice adalah dokumen keuangan; menghapus pelanggan yang sudah
        // pernah ditagih akan memutus jejaknya.
        if ($customer->invoices()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Pelanggan sudah punya invoice dan tidak bisa dihapus. Nonaktifkan saja.');

            return;
        }

        ActivityLogger::log('deleted', $customer, "Hapus pelanggan {$customer->name}");
        $customer->delete();

        $this->dispatch('toast', type: 'success', message: 'Pelanggan dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'pic_name' => '',
            'npwp' => '',
            'power_meter_id' => null,
            'tariff_group_id' => null,
            'daya_kva' => 0,
            'biaya_beban_mode' => 'flat',
            'biaya_beban' => 0,
            'billing_day' => null,
            'contract_start' => null,
            'contract_end' => null,
            'status' => 'active',
            'notes' => '',
        ];
        $this->resetErrorBag();
    }

    public function render()
    {
        $customers = Customer::query()
            ->with(['powerMeter:id,code,name', 'tariffGroup:id,code'])
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('address', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.master.customer-page', [
            'customers' => $customers,
            'usageThisMonth' => $this->usageThisMonth($customers->pluck('power_meter_id')->filter()->all()),
            // Meter yang belum dipakai pelanggan lain, plus meter milik
            // pelanggan yang sedang diedit agar tetap muncul terpilih.
            'availableMeters' => PowerMeter::query()
                ->where(function ($q) {
                    $q->whereDoesntHave('customer')
                        ->when($this->editingId, fn ($sub) => $sub->orWhereHas('customer', fn ($c) => $c->where('customers.id', $this->editingId)));
                })
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'tariffGroups' => TariffGroup::active()->orderBy('code')->get(['id', 'code', 'name']),
            'defaultBillingDay' => (int) setting('billing_cut_off_day', 1),
        ]);
    }

    /**
     * Pemakaian kWh bulan berjalan per meter, diambil sekali untuk seluruh
     * baris di halaman agar tidak menimbulkan query N+1.
     *
     * @param  array<int>  $meterIds
     * @return array<int, float>
     */
    private function usageThisMonth(array $meterIds): array
    {
        if (empty($meterIds)) {
            return [];
        }

        return MeterReadingDaily::query()
            ->whereIn('power_meter_id', $meterIds)
            ->between(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
            ->selectRaw('power_meter_id, SUM(kwh_lwbp + kwh_wbp) AS total')
            ->groupBy('power_meter_id')
            ->pluck('total', 'power_meter_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
