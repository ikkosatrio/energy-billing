<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="Nama, username, atau email…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="spacer"></div>
            @can('user.create')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="user-plus" style="width:15px;height:15px"></i>
                    Tambah User
                </button>
            @endcan
        </div>
    </div>

    <div class="grid" style="grid-template-columns:1.5fr 1fr">
        <div class="card">
            <div class="card-title" style="margin-bottom:14px">Pengguna</div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Login Terakhir</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="strong">{{ $user->name }}</td>
                                <td class="mono">{{ $user->username }}</td>
                                <td class="text-muted">{{ $user->email }}</td>
                                <td><span class="badge badge-info">{{ $user->role?->name ?? '—' }}</span></td>
                                <td class="text-muted mono">
                                    {{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}
                                </td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-neutral' }}">
                                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-right nowrap">
                                    @can('user.update')
                                        <span class="link-action" wire:click="edit({{ $user->id }})" style="margin-right:12px">Ubah</span>
                                    @endcan
                                    @can('user.delete')
                                        @if ($user->id !== auth()->id())
                                            <span class="link-action danger" x-on:click="ConfirmDialog.show({
                                                    title: 'Hapus user ' + @js($user->username) + '?',
                                                    danger: true,
                                                    confirmText: 'Ya, Hapus',
                                                    onConfirm: () => $wire.delete({{ $user->id }}),
                                                })">Hapus</span>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="table-empty">Tidak ada user yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div style="margin-top:16px">{{ $users->links() }}</div>
            @endif
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:14px">Role &amp; Hak Akses</div>
            <div class="stack" style="gap:12px">
                @foreach ($roles as $role)
                    <div style="border:1px solid var(--border-muted);border-radius:10px;padding:14px">
                        <div style="font-size:14px;font-weight:700;margin-bottom:4px">{{ $role->name }}</div>
                        <div class="text-muted" style="font-size:12px;line-height:1.6">{{ $role->description }}</div>
                    </div>
                @endforeach
            </div>
            @can('role.view')
                <a href="{{ route('system.roles.index') }}" wire:navigate class="link-action" style="display:inline-block;margin-top:14px">
                    Kelola hak akses →
                </a>
            @endcan
        </div>
    </div>

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="modal modal-sm">
                <div class="card-title" style="margin-bottom:20px">
                    {{ $editingId ? 'Ubah User' : 'Tambah User' }}
                </div>

                <form wire:submit="save">
                    <div class="field">
                        <label class="field-label">Nama <span style="color:var(--danger)">*</span></label>
                        <input type="text" class="input @error('form.name') is-invalid @enderror" wire:model="form.name">
                        @error('form.name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Username <span style="color:var(--danger)">*</span></label>
                        <input type="text" class="input mono @error('form.username') is-invalid @enderror" wire:model="form.username">
                        @error('form.username') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Email <span style="color:var(--danger)">*</span></label>
                        <input type="email" class="input @error('form.email') is-invalid @enderror" wire:model="form.email">
                        @error('form.email') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Role <span style="color:var(--danger)">*</span></label>
                        <x-select-search
                            wire:model="form.role_id"
                            :invalid="$errors->has('form.role_id')"
                            placeholder="— pilih role —"
                            search-placeholder="Cari role…"
                            :options="$roles->map(fn ($role) => [
                                'value' => $role->id,
                                'label' => $role->name,
                                'sub' => $role->description,
                            ])" />
                        @error('form.role_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Telepon</label>
                        <input type="text" class="input" wire:model="form.phone">
                    </div>

                    <div class="field">
                        <label class="field-label">
                            Password {!! $editingId ? '' : '<span style="color:var(--danger)">*</span>' !!}
                        </label>
                        <input type="password" autocomplete="new-password"
                               class="input @error('password') is-invalid @enderror" wire:model="password">
                        @error('password') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">
                            {{ $editingId ? 'Kosongkan bila password tidak diubah.' : 'Minimal 8 karakter.' }}
                        </div>
                    </div>

                    <label class="checkbox-row">
                        <input type="checkbox" wire:model="form.is_active">
                        <span>Akun aktif</span>
                    </label>

                    <div class="row" style="margin-top:24px">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showForm', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
