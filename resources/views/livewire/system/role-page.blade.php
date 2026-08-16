<div>

    <div class="card mb-18">
        <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div class="card-title">Role &amp; Hak Akses</div>
                <div class="card-sub">
                    Hak akses ditentukan per role. Super Admin selalu punya akses penuh dan
                    daftar izinnya tidak perlu diatur.
                </div>
            </div>
            @can('role.manage')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="shield-plus" style="width:15px;height:15px"></i>
                    Tambah Role
                </button>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Slug</th>
                        <th>Keterangan</th>
                        <th class="num">Izin</th>
                        <th class="num">User</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="strong">
                                {{ $role->name }}
                                @if ($role->is_system)
                                    <span class="badge badge-neutral" style="margin-left:8px">Bawaan</span>
                                @endif
                            </td>
                            <td class="mono text-muted">{{ $role->slug }}</td>
                            <td class="text-muted">{{ $role->description }}</td>
                            <td class="num">
                                {{ $role->isSuperAdmin() ? 'Semua' : $role->permissions_count }}
                            </td>
                            <td class="num">{{ $role->users_count }}</td>
                            <td class="text-right nowrap">
                                @can('role.manage')
                                    <span class="link-action" wire:click="edit({{ $role->id }})" style="margin-right:12px">Ubah</span>
                                    @unless ($role->is_system)
                                        <span class="link-action danger" x-on:click="ConfirmDialog.show({
                                                title: 'Hapus role ' + @js($role->name) + '?',
                                                danger: true,
                                                confirmText: 'Ya, Hapus',
                                                onConfirm: () => $wire.delete({{ $role->id }}),
                                            })">Hapus</span>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="modal">
                <div class="card-title" style="margin-bottom:20px">
                    {{ $editingId ? 'Ubah Role' : 'Tambah Role' }}
                </div>

                <form wire:submit="save">
                    <div class="form-grid form-grid-2">
                        <div class="field">
                            <label class="field-label">Nama Role <span style="color:var(--danger)">*</span></label>
                            <input type="text" class="input @error('form.name') is-invalid @enderror" wire:model="form.name">
                            @error('form.name') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="field">
                            <label class="field-label">Slug <span style="color:var(--danger)">*</span></label>
                            <input type="text" class="input mono @error('form.slug') is-invalid @enderror"
                                   wire:model="form.slug" placeholder="billing-staff">
                            @error('form.slug') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label">Keterangan</label>
                        <input type="text" class="input" wire:model="form.description">
                    </div>

                    @if ($editingRole?->isSuperAdmin())
                        <div class="alert alert-info" style="margin-top:18px">
                            Super Admin punya akses penuh ke seluruh modul. Daftar izinnya tidak bisa
                            dan tidak perlu dibatasi.
                        </div>
                    @else
                        <div style="margin-top:20px">
                            <div class="field-label" style="margin-bottom:12px">Hak Akses</div>

                            @foreach ($permissionGroups as $group => $permissions)
                                <div style="margin-bottom:16px">
                                    <div style="font-size:13px;font-weight:700;margin-bottom:8px">{{ $group }}</div>
                                    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px">
                                        @foreach ($permissions as $permission)
                                            <label class="checkbox-row" style="margin:0">
                                                <input type="checkbox" value="{{ $permission->id }}" wire:model="selected">
                                                <span>{{ $permission->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="row" style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border-soft)">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showForm', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
