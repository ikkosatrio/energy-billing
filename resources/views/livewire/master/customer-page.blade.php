<div>

    {{-- ── Filter ──────────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="Nama, kode, atau alamat…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="field">
                <label class="field-label">Status</label>
                <x-select-search wire:model.live="statusFilter" placeholder="Semua status"
                    :options="[
                        ['value' => '', 'label' => 'Semua status'],
                        ['value' => 'active', 'label' => 'Aktif'],
                        ['value' => 'inactive', 'label' => 'Nonaktif'],
                    ]" />
            </div>
            <div class="spacer"></div>
            @can('customer.create')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="user-plus" style="width:15px;height:15px"></i>
                    Tambah Pelanggan
                </button>
            @endcan
        </div>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pelanggan</th>
                        <th>Alamat Gudang</th>
                        <th>Meter</th>
                        <th>Golongan</th>
                        <th>Tgl Tagih</th>
                        <th class="num">kWh Bulan Ini</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="strong">
                                {{ $customer->name }}
                                <div class="sub">{{ $customer->code }}{{ $customer->pic_name ? ' · '.$customer->pic_name : '' }}</div>
                            </td>
                            <td class="text-muted">{{ $customer->address ?: '—' }}</td>
                            <td class="num" style="text-align:left">
                                {{ $customer->powerMeter?->code ?? '— belum terhubung' }}
                            </td>
                            <td>{{ $customer->tariffGroup?->code ?? '—' }}</td>
                            <td class="text-muted">
                                Tiap tgl {{ $customer->billing_day ?? $defaultBillingDay }}
                                @unless ($customer->billing_day)
                                    <div class="sub">default</div>
                                @endunless
                            </td>
                            <td class="num">
                                {{ kwh($usageThisMonth[$customer->power_meter_id] ?? 0) }}
                            </td>
                            <td>
                                <span class="badge {{ $customer->status === 'active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $customer->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-right nowrap">
                                @can('customer.update')
                                    <span class="link-action" wire:click="edit({{ $customer->id }})" style="margin-right:12px">Ubah</span>
                                @endcan
                                @can('customer.delete')
                                    <span class="link-action danger" x-on:click="ConfirmDialog.show({
                                            title: 'Hapus pelanggan ' + @js($customer->name) + '?',
                                            danger: true,
                                            confirmText: 'Ya, Hapus',
                                            onConfirm: () => $wire.delete({{ $customer->id }}),
                                        })">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty">
                                {{ $search || $statusFilter ? 'Tidak ada pelanggan yang cocok dengan filter.' : 'Belum ada pelanggan. Tambahkan lewat tombol di atas.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div style="margin-top:16px">{{ $customers->links() }}</div>
        @endif
    </div>

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="modal">
                <div class="card-head" style="margin-bottom:20px">
                    <div>
                        <div class="card-title">{{ $editingId ? 'Ubah Pelanggan' : 'Tambah Pelanggan' }}</div>
                        <div class="card-sub">Satu pelanggan memakai tepat satu power meter</div>
                    </div>
                    <button type="button" class="btn-ghost btn" wire:click="$set('showForm', false)">Tutup</button>
                </div>

                <form wire:submit="save">
                    <div class="form-grid form-grid-2">
                        <div>
                            <div class="field">
                                <label class="field-label">Kode <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="input @error('form.code') is-invalid @enderror" wire:model="form.code">
                                @error('form.code') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Nama Pelanggan <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="input @error('form.name') is-invalid @enderror" wire:model="form.name">
                                @error('form.name') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Alamat Gudang</label>
                                <textarea class="textarea" wire:model="form.address"></textarea>
                            </div>

                            <div class="field">
                                <label class="field-label">Nama PIC</label>
                                <input type="text" class="input" wire:model="form.pic_name">
                            </div>

                            <div class="field">
                                <label class="field-label">Telepon</label>
                                <input type="text" class="input" wire:model="form.phone">
                            </div>

                            <div class="field">
                                <label class="field-label">Email</label>
                                <input type="email" class="input @error('form.email') is-invalid @enderror" wire:model="form.email">
                                @error('form.email') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">NPWP</label>
                                <input type="text" class="input mono" wire:model="form.npwp">
                            </div>
                        </div>

                        <div>
                            <div class="field">
                                <label class="field-label">Power Meter</label>
                                <x-select-search
                                    wire:model="form.power_meter_id"
                                    :invalid="$errors->has('form.power_meter_id')"
                                    placeholder="— belum terhubung —"
                                    search-placeholder="Cari kode atau nama meter…"
                                    :options="$availableMeters
                                        ->map(fn ($meter) => [
                                            'value' => $meter->id,
                                            'label' => $meter->code.' — '.$meter->name,
                                            'sub' => 'ID meter '.$meter->id,
                                        ])
                                        ->prepend(['value' => '', 'label' => '— belum terhubung —'])" />
                                @error('form.power_meter_id') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Golongan Tarif</label>
                                <x-select-search
                                    wire:model="form.tariff_group_id"
                                    placeholder="— pilih golongan —"
                                    search-placeholder="Cari golongan tarif…"
                                    :options="$tariffGroups
                                        ->map(fn ($group) => [
                                            'value' => $group->id,
                                            'label' => $group->code,
                                            'sub' => $group->name,
                                        ])
                                        ->prepend(['value' => '', 'label' => '— pilih golongan —'])" />
                            </div>

                            <div class="field">
                                <label class="field-label">Daya Tersambung (kVA)</label>
                                <input type="number" step="0.01" min="0" class="input mono" wire:model="form.daya_kva">
                            </div>

                            <div class="field">
                                <label class="field-label">Mode Biaya Beban</label>
                                <x-select-search wire:model.live="form.biaya_beban_mode"
                                    :options="[
                                        ['value' => 'flat', 'label' => 'Nominal flat'],
                                        ['value' => 'per_kva', 'label' => 'kVA × tarif beban golongan'],
                                    ]" />
                            </div>

                            @if ($form['biaya_beban_mode'] === 'flat')
                                <div class="field">
                                    <label class="field-label">Biaya Beban (Rp)</label>
                                    <input type="number" step="1" min="0" class="input mono" wire:model="form.biaya_beban">
                                </div>
                            @else
                                <div class="alert alert-info" style="margin-top:14px">
                                    Biaya beban dihitung otomatis: daya kVA × tarif beban per kVA pada golongan
                                    tarif yang berlaku saat invoice digenerate.
                                </div>
                            @endif

                            <div class="field">
                                <label class="field-label">Tanggal Tagih</label>
                                <input type="number" min="1" max="28" class="input mono @error('form.billing_day') is-invalid @enderror"
                                       placeholder="Kosongkan = ikut default (tgl {{ $defaultBillingDay }})"
                                       wire:model="form.billing_day">
                                @error('form.billing_day') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Mulai Kontrak</label>
                                <input type="date" class="input" wire:model="form.contract_start">
                            </div>

                            <div class="field">
                                <label class="field-label">Akhir Kontrak</label>
                                <input type="date" class="input @error('form.contract_end') is-invalid @enderror" wire:model="form.contract_end">
                                @error('form.contract_end') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Status</label>
                                <x-select-search wire:model="form.status"
                                    :options="[
                                        ['value' => 'active', 'label' => 'Aktif'],
                                        ['value' => 'inactive', 'label' => 'Nonaktif'],
                                    ]" />
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label">Catatan</label>
                        <textarea class="textarea" wire:model="form.notes"></textarea>
                    </div>

                    <div class="row" style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border-soft)">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <i data-lucide="check" style="width:15px;height:15px"></i>
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan…</span>
                        </button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showForm', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
