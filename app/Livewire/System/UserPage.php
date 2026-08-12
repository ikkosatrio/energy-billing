<?php

namespace App\Livewire\System;

use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithPagination;

class UserPage extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingId = null;

    public bool $showForm = false;

    public array $form = [];

    public string $password = '';

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($this->editingId)],
            // 'email:filter' menolak CRLF; lihat catatan keamanan di README.
            'form.email' => ['required', 'email:filter', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.role_id' => ['required', 'exists:roles,id'],
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.is_active' => ['boolean'],
            // Wajib saat membuat user baru; saat mengubah, kosong berarti
            // password lama dipertahankan.
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nama',
            'form.username' => 'username',
            'form.email' => 'email',
            'form.role_id' => 'role',
            'password' => 'password',
        ];
    }

    public function create(): void
    {
        $this->authorize('user.create');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('user.update');

        $user = User::findOrFail($id);

        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
        ];
        $this->password = '';

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'user.update' : 'user.create');

        $validated = $this->validate();
        $data = $validated['form'];

        if ($this->password !== '') {
            $data['password'] = $this->password;
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);

            // Mencegah admin terakhir mengunci dirinya sendiri di luar sistem.
            if ($user->id === auth()->id() && !$data['is_active']) {
                $this->dispatch('toast', type: 'error', message: 'Anda tidak bisa menonaktifkan akun sendiri.');

                return;
            }

            $user->fill($data);
            ActivityLogger::logModelChange('updated', $user, "Ubah user {$user->username}");
            $user->save();
        } else {
            $user = User::create($data);
            ActivityLogger::log('created', $user, "Tambah user {$user->username}");
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Data user tersimpan.');
    }

    public function delete(int $id): void
    {
        $this->authorize('user.delete');

        if ($id === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak bisa menghapus akun sendiri.');

            return;
        }

        $user = User::findOrFail($id);

        ActivityLogger::log('deleted', $user, "Hapus user {$user->username}");
        $user->delete();

        $this->dispatch('toast', type: 'success', message: 'User dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'username' => '',
            'email' => '',
            'role_id' => null,
            'phone' => '',
            'is_active' => true,
        ];
        $this->password = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.system.user-page', [
            'users' => User::query()
                ->with('role:id,name')
                ->when($this->search, fn ($q) => $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('username', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                }))
                ->orderBy('name')
                ->paginate(15),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }
}
