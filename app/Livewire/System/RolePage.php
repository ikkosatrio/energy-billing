<?php

namespace App\Livewire\System;

use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RolePage extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public array $form = [];

    /** ID permission yang tercentang pada form. */
    public array $selected = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:100'],
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

        $role = Role::with('permissions:id')->findOrFail($id);

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
            $role = Role::findOrFail($this->editingId);
            $role->fill($data);
            ActivityLogger::logModelChange('updated', $role, "Ubah role {$role->name}");
            $role->save();
        } else {
            $role = Role::create($data);
            ActivityLogger::log('created', $role, "Tambah role {$role->name}");
        }

        // Super admin diloloskan lewat Gate::before, jadi daftar
        // permission-nya tidak perlu — dan tidak boleh — dibatasi di sini.
        if (!$role->isSuperAdmin()) {
            $role->permissions()->sync($this->selected);
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Role tersimpan.');
    }

    public function delete(int $id): void
    {
        $this->authorize('role.manage');

        $role = Role::withCount('users')->findOrFail($id);

        if ($role->is_system) {
            $this->dispatch('toast', type: 'error', message: 'Role bawaan sistem tidak bisa dihapus.');

            return;
        }

        if ($role->users_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Role masih dipakai user. Pindahkan user-nya dulu.');

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
        return view('livewire.system.role-page', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'permissionGroups' => Permission::orderBy('id')->get()->groupBy('group'),
            'editingRole' => $this->editingId ? Role::find($this->editingId) : null,
        ]);
    }
}
