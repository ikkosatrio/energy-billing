<?php

namespace App\Livewire\Tariff;

use App\Models\TariffGroup;
use App\Models\TariffRate;
use App\Services\ActivityLogger;
use App\Services\Tariff\TariffService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TariffGroupPage extends Component
{
    public string $search = '';

    // --- Form golongan ---
    public bool $showGroupForm = false;

    public ?int $editingGroupId = null;

    public array $groupForm = [];

    // --- Form tarif baru ---
    public bool $showRateForm = false;

    public ?int $rateGroupId = null;

    public array $rateForm = [];

    // --- Riwayat tarif ---
    public ?int $historyGroupId = null;

    public function mount(): void
    {
        $this->resetGroupForm();
        $this->resetRateForm();
    }

    protected function rules(): array
    {
        return [
            'groupForm.code' => ['required', 'string', 'max:50', Rule::unique('tariff_groups', 'code')->ignore($this->editingGroupId)],
            'groupForm.name' => ['required', 'string', 'max:255'],
            'groupForm.description' => ['nullable', 'string', 'max:255'],
            'groupForm.is_active' => ['boolean'],

            'rateForm.rate_lwbp' => ['required', 'numeric', 'min:0'],
            'rateForm.rate_wbp' => ['required', 'numeric', 'min:0'],
            'rateForm.rate_beban_per_kva' => ['nullable', 'numeric', 'min:0'],
            'rateForm.effective_from' => ['required', 'date'],
            'rateForm.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'groupForm.code' => 'kode golongan',
            'groupForm.name' => 'nama golongan',
            'rateForm.rate_lwbp' => 'tarif LWBP',
            'rateForm.rate_wbp' => 'tarif WBP',
            'rateForm.effective_from' => 'tanggal mulai berlaku',
        ];
    }

    // ── Golongan ─────────────────────────────────────────────────────────

    public function createGroup(): void
    {
        $this->authorize('tariff.create');

        $this->resetGroupForm();
        $this->showGroupForm = true;
    }

    public function editGroup(int $id): void
    {
        $this->authorize('tariff.update');

        $group = TariffGroup::findOrFail($id);

        $this->editingGroupId = $group->id;
        $this->groupForm = [
            'code' => $group->code,
            'name' => $group->name,
            'description' => $group->description,
            'is_active' => $group->is_active,
        ];

        $this->resetErrorBag();
        $this->showGroupForm = true;
    }

    public function saveGroup(): void
    {
        $this->authorize($this->editingGroupId ? 'tariff.update' : 'tariff.create');

        $data = $this->validate($this->onlyRulesFor('groupForm'))['groupForm'];

        if ($this->editingGroupId) {
            $group = TariffGroup::findOrFail($this->editingGroupId);
            $group->fill($data);
            ActivityLogger::logModelChange('updated', $group, "Ubah golongan tarif {$group->code}");
            $group->save();
        } else {
            $group = TariffGroup::create($data);
            ActivityLogger::log('created', $group, "Tambah golongan tarif {$group->code}");
        }

        $this->showGroupForm = false;
        $this->dispatch('toast', type: 'success', message: 'Golongan tarif tersimpan.');
    }

    public function deleteGroup(int $id): void
    {
        $this->authorize('tariff.delete');

        $group = TariffGroup::findOrFail($id);

        if ($group->customers()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Golongan masih dipakai pelanggan. Nonaktifkan saja.');

            return;
        }

        ActivityLogger::log('deleted', $group, "Hapus golongan tarif {$group->code}");
        $group->delete();

        $this->dispatch('toast', type: 'success', message: 'Golongan tarif dihapus.');
    }

    // ── Tarif ────────────────────────────────────────────────────────────

    public function newRate(int $groupId): void
    {
        $this->authorize('tariff.create');

        $group = TariffGroup::findOrFail($groupId);
        $current = $group->rateOn();

        $this->rateGroupId = $groupId;
        // Prefill dari tarif berjalan supaya operator cukup mengubah angka
        // yang benar-benar berubah.
        $this->rateForm = [
            'rate_lwbp' => $current?->rate_lwbp ?? 0,
            'rate_wbp' => $current?->rate_wbp ?? 0,
            'rate_beban_per_kva' => $current?->rate_beban_per_kva ?? 0,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'notes' => '',
        ];

        $this->resetErrorBag();
        $this->showRateForm = true;
    }

    public function saveRate(TariffService $tariff): void
    {
        $this->authorize('tariff.create');

        $data = $this->validate($this->onlyRulesFor('rateForm'))['rateForm'];

        $group = TariffGroup::findOrFail($this->rateGroupId);
        $rate = $tariff->publishRate($group, $data);

        ActivityLogger::log(
            'created',
            $rate,
            "Terbitkan tarif baru {$group->code} berlaku {$data['effective_from']}",
            newValues: $data,
        );

        $this->showRateForm = false;
        $this->dispatch('toast', type: 'success', message: 'Tarif baru diterbitkan. Tarif lama ditutup otomatis.');
    }

    public function showHistory(int $groupId): void
    {
        $this->historyGroupId = $groupId;
    }

    // ── Helper ───────────────────────────────────────────────────────────

    /**
     * Memvalidasi hanya satu form. Tanpa ini, menyimpan form golongan akan
     * ikut memvalidasi field tarif yang belum terisi (dan sebaliknya).
     */
    private function onlyRulesFor(string $prefix): array
    {
        return array_filter(
            $this->rules(),
            fn ($key) => str_starts_with($key, $prefix.'.'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function resetGroupForm(): void
    {
        $this->editingGroupId = null;
        $this->groupForm = ['code' => '', 'name' => '', 'description' => '', 'is_active' => true];
        $this->resetErrorBag();
    }

    private function resetRateForm(): void
    {
        $this->rateGroupId = null;
        $this->rateForm = [
            'rate_lwbp' => 0,
            'rate_wbp' => 0,
            'rate_beban_per_kva' => 0,
            'effective_from' => now()->toDateString(),
            'notes' => '',
        ];
    }

    public function render()
    {
        $groups = TariffGroup::query()
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%");
            }))
            ->withCount('customers')
            ->orderBy('code')
            ->get();

        $today = now()->toDateString();

        return view('livewire.tariff.tariff-group-page', [
            'groups' => $groups,
            // Tarif berjalan tiap golongan, diambil sekali untuk semua baris.
            'currentRates' => TariffRate::query()
                ->whereIn('tariff_group_id', $groups->pluck('id'))
                ->effectiveOn($today)
                ->get()
                ->keyBy('tariff_group_id'),
            'history' => $this->historyGroupId
                ? TariffRate::where('tariff_group_id', $this->historyGroupId)->orderByDesc('effective_from')->get()
                : collect(),
            'historyGroup' => $this->historyGroupId ? TariffGroup::find($this->historyGroupId) : null,
        ]);
    }
}
