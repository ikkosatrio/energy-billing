<?php

namespace App\Livewire\System;

use App\Models\CustomerUser;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Kelola role untuk dua audiens sekaligus, dipisah tab `guardTab`:
 * staf ('web') dan portal pelanggan ('customer').
 *
 * Satu halaman, bukan dua: mekanismenya identik, dan yang benar-benar
 * berbeda hanya daftar permission yang boleh dipilih. Memisahkan permission
 * per guard bukan soal kerapian tampilan — tanpa itu, `user.delete` bisa
 * tercentang pada role pelanggan.
 */
class RolePage extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    /** 'web' = role staf, 'customer' = role portal pelanggan. */
    public string $guardTab = 'web';

    public array $form = [];

    /** ID permission yang tercentang pada form. */
    public array $selected = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    /**
     * Tab di luar dua nilai yang sah hanya bisa datang dari payload yang
     * dikarang; dikembalikan ke staf daripada dipakai sebagai nilai guard.
     */
    public function updatedGuardTab(string $value): void
    {
        if (!in_array($value, ['web', CustomerUser::GUARD], true)) {
            $this->guardTab = 'web';
        }

        $this->showForm = false;
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:100'],
            // Slug unique lintas guard karena indeksnya global — role portal
            // dianjurkan berawalan `portal-` supaya tidak saling menyerobot.
            'form.slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')->ignore($this->editingId)],
            'form.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.name' => 'nama role', 'form.slug' => 'slug'];
    }

    protected function messages(): array
    {
        return ['form.slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.'];
    }

    public function create(): void
    {
        $this->authorize('role.manage');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('role.manage');

        // Dibatasi guard tab yang sedang aktif: membuka role portal dari tab
        // staf akan menampilkan daftar centang permission yang salah, dan
        // menyimpannya berarti menghapus seluruh permission role itu.
        $role = Role::with('permissions:id')
            ->forGuard($this->guardTab)
            ->findOrFail($id);

        $this->editingId = $role->id;
        $this->form = [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
        ];
        $this->selected = $role->permissions->pluck('id')->all();

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('role.manage');

        $data = $this->validate()['form'];

        if ($this->editingId) {
            $role = Role::forGuard($this->guardTab)->findOrFail($this->editingId);
            $role->fill($data);
            ActivityLogger::logModelChange('updated', $role, "Ubah role {$role->name}");
            $role->save();
        } else {
            $role = Role::create($data + ['guard' => $this->guardTab]);
            ActivityLogger::log('created', $role, "Tambah role {$role->name}");
        }

        // Super admin diloloskan lewat Gate::before, jadi daftar
        // permission-nya tidak perlu — dan tidak boleh — dibatasi di sini.
        if (!$role->isSuperAdmin()) {
            // Disaring ulang terhadap permission milik guard ini, bukan
            // dipercaya apa adanya: array $selected datang dari browser dan
            // bisa memuat id permission guard lain.
            $role->permissions()->sync(
                Permission::forGuard($role->guard)
                    ->whereIn('id', $this->selected)
                    ->pluck('id'),
            );
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Role tersimpan.');
    }

    public function delete(int $id): void
    {
        $this->authorize('role.manage');

        $role = Role::withCount(['users', 'customerUsers'])
            ->forGuard($this->guardTab)
            ->findOrFail($id);

        if ($role->is_system) {
            $this->dispatch('toast', type: 'error', message: 'Role bawaan sistem tidak bisa dihapus.');

            return;
        }

        $inUse = $role->users_count + $role->customer_users_count;

        if ($inUse > 0) {
            $this->dispatch('toast', type: 'error', message: 'Role masih dipakai akun. Pindahkan akunnya dulu.');

            return;
        }

        ActivityLogger::log('deleted', $role, "Hapus role {$role->name}");
        $role->delete();

        $this->dispatch('toast', type: 'success', message: 'Role dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'slug' => '', 'description' => ''];
        $this->selected = [];
        $this->resetErrorBag();
    }

    public function render()
    {
        $isPortal = $this->guardTab === CustomerUser::GUARD;

        return view('livewire.system.role-page', [
            'roles' => Role::withCount(['users', 'customerUsers', 'permissions'])
                ->forGuard($this->guardTab)
                ->orderBy('name')
                ->get(),
            'permissionGroups' => Permission::forGuard($this->guardTab)
                ->orderBy('id')
                ->get()
                ->groupBy('group'),
            'editingRole' => $this->editingId
                ? Role::forGuard($this->guardTab)->find($this->editingId)
                : null,
            'isPortal' => $isPortal,
            'guardLabel' => $isPortal ? 'portal pelanggan' : 'staf',
        ]);
    }
}
