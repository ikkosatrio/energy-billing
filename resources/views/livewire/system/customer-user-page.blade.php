<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="Nama, username, atau email…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="spacer"></div>
            @can('customer_user.manage')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="user-plus" style="width:15px;height:15px"></i>
                    Tambah Akun Portal
                </button>
            @endcan
        </div>
    </div>

    <div class="alert alert-info mb-18">
        Akun portal punya dua pengaturan yang keduanya perlu: <strong>role</strong> menentukan menu
        mana yang terbuka, <strong>pelanggan</strong> menentukan data siapa yang terlihat. Akun tanpa
        pelanggan tidak bisa login.
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role Portal</th>
                        <th>Pelanggan Diakses</th>
                        <th>Login Terakhir</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $akun)
                        <tr>
                            <td class="strong">
                                {{ $akun->name }}
                                @if ($akun->email)
                                    <div class="text-muted" style="font-size:12px">{{ $akun->email }}</div>
                                @endif
                            </td>
                            <td class="mono">{{ $akun->username }}</td>
                            <td>
                                <span class="badge badge-info">{{ $akun->role?->name ?? 'Tanpa role' }}</span>
                            </td>
                            <td>
                                @if ($akun->customers->isEmpty())
                                    {{-- Tidak bisa login sama sekali — ditandai
                                         jelas supaya tidak terbaca sebagai akun
                                         yang siap dipakai. --}}
                                    <span class="badge badge-danger">Belum diatur</span>
                                @else
                                    <div style="font-size:12px;line-height:1.7">
                                        {{ $akun->customers->pluck('name')->implode(', ') }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-muted mono">
                                {{ $akun->last_login_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}
                            </td>
                            <td>
                                <span class="badge {{ $akun->is_active ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $akun->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-right nowrap">
                                @can('customer_user.manage')
                                    <span class="link-action" wire:click="edit({{ $akun->id }})" style="margin-right:12px">Ubah</span>

                                    <span class="link-action" style="margin-right:12px"
                                          x-on:click="ConfirmDialog.show({
                                                  title: @js($akun->is_active ? 'Nonaktifkan akun '.$akun->username.'?' : 'Aktifkan akun '.$akun->username.'?'),
                                                  text: @js($akun->is_active
                                                      ? 'Akun ini tidak akan bisa login sampai diaktifkan kembali. Data dan daftar pelanggannya tetap tersimpan.'
                                                      : 'Akun ini bisa login kembali ke portal.'),
                                                  danger: {{ $akun->is_active ? 'true' : 'false' }},
                                                  confirmText: @js($akun->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan'),
                                                  onConfirm: () => $wire.toggleActive({{ $akun->id }}),
                                              })">
                                        {{ $akun->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </span>

                                    <span class="link-action danger" x-on:click="ConfirmDialog.show({
                                            title: 'Hapus akun ' + @js($akun->username) + '?',
                                            text: 'Menonaktifkan biasanya lebih tepat — akun yang dihapus tidak bisa login dan namanya hilang dari daftar, walau jejaknya di log aktivitas tetap ada.',
                                            danger: true,
                                            confirmText: 'Ya, Hapus',
                                            onConfirm: () => $wire.delete({{ $akun->id }}),
                                        })">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="table-empty">
                                Belum ada akun portal. Tambahkan satu untuk memberi pelanggan akses
                                melihat pemakaian dan tagihannya sendiri.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($accounts->hasPages())
            <div style="margin-top:16px">{{ $accounts->links() }}</div>
        @endif
    </div>

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="modal modal-sm">
                <div class="card-title" style="margin-bottom:20px">
                    {{ $editingId ? 'Ubah Akun Portal' : 'Tambah Akun Portal' }}
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
                        <label class="field-label">Email</label>
                        <input type="email" class="input @error('form.email') is-invalid @enderror" wire:model="form.email">
                        @error('form.email') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">Opsional. Bila diisi, bisa dipakai untuk login selain username.</div>
                    </div>

                    <div class="field">
                        <label class="field-label">Role Portal <span style="color:var(--danger)">*</span></label>
                        <x-select-search
                            wire:model="form.role_id"
                            :invalid="$errors->has('form.role_id')"
                            placeholder="— pilih role —"
                            search-placeholder="Cari role portal…"
                            :options="$roles->map(fn ($role) => [
                                'value' => $role->id,
                                'label' => $role->name,
                                'sub' => $role->description,
                            ])" />
                        @error('form.role_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    {{-- Batas data, bukan hak fitur — dua hal berbeda, jadi
                         diletakkan sebagai blok tersendiri agar tidak terbaca
                         sebagai bagian dari role di atas. --}}
                    <div class="field">
                        <label class="field-label">
                            Pelanggan yang Bisa Diakses <span style="color:var(--danger)">*</span>
                        </label>
                        <div style="border:1px solid {{ $errors->has('selectedCustomers') ? 'var(--danger)' : 'var(--border)' }};border-radius:10px;padding:12px;max-height:220px;overflow-y:auto">
                            @forelse ($customers as $customer)
                                <label class="checkbox-row" style="margin-top:{{ $loop->first ? '0' : '10px' }}">
                                    <input type="checkbox" value="{{ $customer->id }}" wire:model="selectedCustomers">
                                    <span style="flex:1;min-width:0">
                                        {{ $customer->name }}
                                        <span class="text-faint mono" style="font-size:11px">· {{ $customer->code }}</span>
                                        @unless ($customer->power_meter_id)
                                            <span class="badge badge-warning" style="margin-left:6px">tanpa meter</span>
                                        @endunless
                                    </span>
                                </label>
                            @empty
                                <div class="text-muted" style="font-size:12px">Belum ada pelanggan terdaftar.</div>
                            @endforelse
                        </div>
                        @error('selectedCustomers') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">
                            Boleh lebih dari satu — mis. satu grup usaha dengan beberapa cabang.
                            Pelanggan tanpa meter tetap bisa dipilih, tapi halaman monitoringnya akan kosong.
                        </div>
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
                            {{ $editingId
                                ? 'Kosongkan bila password tidak diubah. Isi untuk mengatur ulang password pelanggan.'
                                : 'Minimal 8 karakter. Sampaikan ke pelanggan lewat saluran yang aman.' }}
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
