<?php

namespace App\Livewire\System;

use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kelola akun login portal pelanggan.
 *
 * Dua hal diatur per akun, dan keduanya perlu:
 *   role      — menentukan menu portal mana yang terbuka
 *   pelanggan — menentukan data siapa yang terlihat
 *
 * Akun tanpa pelanggan tidak bisa login sama sekali (lihat
 * Portal\LoginController) — jadi daftar pelanggan wajib diisi minimal satu,
 * ditegakkan di validasi supaya kesalahannya terlihat saat menyimpan, bukan
 * baru saat pelanggan gagal masuk.
 *
 * Password hanya bisa diatur ulang dari sini: tidak ada alur "lupa password"
 * mandiri, jadi mengosongkan kolom password saat mengubah berarti password
 * lama dipertahankan.
 */
class CustomerUserPage extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editingId = null;

    public bool $showForm = false;

    public array $form = [];

    public string $password = '';

    /** @var array<int, string> id pelanggan yang dicentang pada form */
    public array $selectedCustomers = [];

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
            'form.username' => ['required', 'string', 'max:50',
                Rule::unique('customer_users', 'username')->ignore($this->editingId)],
            // Boleh kosong — banyak pelanggan tidak punya email khusus dan
            // login memakai username. 'email:filter' menolak CRLF.
            'form.email' => ['nullable', 'email:filter', 'max:255',
                Rule::unique('customer_users', 'email')->ignore($this->editingId)],
            // Dibatasi guard portal: role staf di akun portal hanya
            // menghasilkan akun yang tidak bisa membuka apa pun di portal,
            // sekaligus memberinya slug permission yang bukan haknya.
            'form.role_id' => ['required', Rule::exists('roles', 'id')
                ->where('guard', CustomerUser::GUARD)],
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.is_active' => ['boolean'],
            'selectedCustomers' => ['required', 'array', 'min:1'],
            'selectedCustomers.*' => ['exists:customers,id'],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => 'nama',
            'form.username' => 'username',
            'form.email' => 'email',
            'form.role_id' => 'role portal',
            'selectedCustomers' => 'pelanggan',
            'password' => 'password',
        ];
    }

    protected function messages(): array
    {
        return [
            'selectedCustomers.required' => 'Pilih minimal satu pelanggan — akun tanpa pelanggan tidak bisa login.',
            'selectedCustomers.min' => 'Pilih minimal satu pelanggan — akun tanpa pelanggan tidak bisa login.',
        ];
    }

    public function create(): void
    {
        $this->authorize('customer_user.manage');

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('customer_user.manage');

        $akun = CustomerUser::with('customers:id')->findOrFail($id);

        $this->editingId = $akun->id;
        $this->form = [
            'name' => $akun->name,
            'username' => $akun->username,
            'email' => $akun->email,
            'role_id' => $akun->role_id,
            'phone' => $akun->phone,
            'is_active' => $akun->is_active,
        ];
        $this->selectedCustomers = $akun->customers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->password = '';

        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('customer_user.manage');

        $validated = $this->validate();
        $data = $validated['form'];

        // Dinormalkan ke null, bukan dibiarkan string kosong.
        //
        // Kolom `email` unique dan nullable: MySQL mengizinkan banyak NULL
        // tapi hanya satu string kosong, jadi menyimpan '' membuat akun KEDUA
        // yang tak beremail gagal disimpan dengan pesan "email sudah dipakai"
        // pada kolom yang tampak jelas kosong.
        //
        // ConvertEmptyStringsToNull tidak menolong di sini: middleware itu
        // bekerja pada input request HTTP, sedangkan properti Livewire diisi
        // lewat jalurnya sendiri.
        $data['email'] = trim((string) ($data['email'] ?? '')) ?: null;
        $data['phone'] = trim((string) ($data['phone'] ?? '')) ?: null;

        if ($this->password !== '') {
            $data['password'] = $this->password;
        }

        if ($this->editingId) {
            $akun = CustomerUser::findOrFail($this->editingId);
            $akun->fill($data);
            ActivityLogger::logModelChange('updated', $akun, "Ubah akun portal {$akun->username}");
            $akun->save();
        } else {
            $data['created_by'] = auth()->id();
            $akun = CustomerUser::create($data);
            ActivityLogger::log('created', $akun, "Tambah akun portal {$akun->username}");
        }

        $akun->customers()->sync(array_map('intval', $validated['selectedCustomers']));

        $this->showForm = false;
        $this->password = '';
        $this->dispatch('toast', type: 'success', message: 'Akun portal tersimpan.');
    }

    /**
     * Menonaktifkan, bukan menghapus.
     *
     * Akun yang dihapus menghilangkan jejak siapa yang pernah membuka data
     * pelanggan mana di log aktivitas. Nonaktif sudah cukup: LoginController
     * menolaknya sebelum sesi terbentuk.
     */
    public function toggleActive(int $id): void
    {
        $this->authorize('customer_user.manage');

        $akun = CustomerUser::findOrFail($id);
        $akun->forceFill(['is_active' => !$akun->is_active])->save();

        ActivityLogger::log(
            $akun->is_active ? 'activated' : 'deactivated',
            $akun,
            ($akun->is_active ? 'Aktifkan' : 'Nonaktifkan')." akun portal {$akun->username}",
        );

        $this->dispatch(
            'toast',
            type: $akun->is_active ? 'success' : 'warning',
            message: "Akun {$akun->username} ".($akun->is_active ? 'diaktifkan.' : 'dinonaktifkan.'),
        );
    }

    public function delete(int $id): void
    {
        $this->authorize('customer_user.manage');

        $akun = CustomerUser::findOrFail($id);

        ActivityLogger::log('deleted', $akun, "Hapus akun portal {$akun->username}");
        // Soft delete: baris log aktivitas yang menunjuk akun ini tetap bisa
        // menampilkan namanya.
        $akun->delete();

        $this->dispatch('toast', type: 'success', message: 'Akun portal dihapus.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'name' => '',
            'username' => '',
            'email' => '',
            'role_id' => Role::portal()->where('slug', 'portal-penuh')->value('id'),
            'phone' => '',
            'is_active' => true,
        ];
        $this->selectedCustomers = [];
        $this->password = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $this->authorize('customer_user.view');

        $akun = CustomerUser::query()
            ->with(['role:id,name', 'customers:id,name'])
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('name', 'like', "%{$this->search}%")
                    ->orWhere('username', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.system.customer-user-page', [
            'accounts' => $akun,
            'roles' => Role::portal()->orderBy('name')->get(['id', 'name', 'description']),
            // Hanya pelanggan yang meternya sudah terpasang: tanpa meter,
            // seluruh halaman monitoring di portal akan kosong.
            'customers' => Customer::orderBy('name')->get(['id', 'code', 'name', 'power_meter_id']),
        ]);
    }
}
