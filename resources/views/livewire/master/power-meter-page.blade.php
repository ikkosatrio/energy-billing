<div>

    {{-- ── Filter ──────────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="Kode, nama, serial, atau lokasi…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="field">
                <label class="field-label">Status</label>
                <x-select-search wire:model.live="statusFilter" placeholder="Semua status"
                    :options="[
                        ['value' => '', 'label' => 'Semua status'],
                        ['value' => 'active', 'label' => 'Aktif'],
                        ['value' => 'maintenance', 'label' => 'Maintenance'],
                        ['value' => 'inactive', 'label' => 'Nonaktif'],
                    ]" />
            </div>
            <div class="spacer"></div>
            @can('meter.create')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="plus" style="width:15px;height:15px"></i>
                    Tambah Perangkat
                </button>
            @endcan
        </div>
    </div>

    <div class="alert alert-info mb-18">
        Kolom <strong>ID Meter</strong> di bawah adalah nilai yang dikirim gateway sebagai
        <span class="mono">meter_id</span> ke <span class="mono">{{ $ingestUrl }}</span>.
        Nilainya tetap dan bisa dilihat kapan saja.
        <a href="{{ $docsUrl }}" target="_blank">Buka dokumentasi API →</a>
    </div>

    {{-- ── Tabel ───────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th class="num" style="width:80px">ID Meter</th>
                        <th>Nama / Kode</th>
                        <th>Tipe</th>
                        <th>Lokasi</th>
                        <th>Pelanggan</th>
                        <th class="num">CT Ratio</th>
                        <th class="num">Stand Akhir</th>
                        <th>Koneksi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($meters as $meter)
                        @php
                            $connection = $meter->connection_status;
                            $badge = match ($connection) {
                                'online' => ['Online', 'badge-success'],
                                'maintenance' => ['Maintenance', 'badge-warning'],
                                default => ['Offline', 'badge-danger'],
                            };
                            $reading = $meter->latestReading;
                        @endphp
                        <tr>
                            <td class="num strong" style="font-size:15px">{{ $meter->id }}</td>
                            <td class="strong">
                                {{ $meter->name }}
                                <div class="sub mono">{{ $meter->code }}</div>
                            </td>
                            <td class="text-muted">{{ trim($meter->brand.' '.$meter->model) ?: '—' }}</td>
                            <td class="text-muted">{{ $meter->location ?: '—' }}</td>
                            <td>{{ $meter->customer?->name ?? '— belum terhubung' }}</td>
                            <td class="num">{{ $meter->ct_ratio ?: '—' }}</td>
                            <td class="num">
                                {{ $reading ? kwh($reading->total_stand) : '—' }}
                                @if ($reading)
                                    <div class="sub">{{ $reading->read_at->translatedFormat('d M H:i') }}</div>
                                @endif
                            </td>
                            <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td class="text-right nowrap">
                                @can('meter.update')
                                    <span class="link-action" wire:click="edit({{ $meter->id }})" style="margin-right:12px">Ubah</span>
                                @endcan
                                @can('meter.delete')
                                    <span class="link-action danger"
                                          wire:click="delete({{ $meter->id }})"
                                          wire:confirm="Hapus power meter {{ $meter->code }}?">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="table-empty">
                                {{ $search || $statusFilter ? 'Tidak ada perangkat yang cocok dengan filter.' : 'Belum ada power meter. Tambahkan lewat tombol di atas.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($meters->hasPages())
            <div style="margin-top:16px">{{ $meters->links() }}</div>
        @endif
    </div>

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="modal">
                <div class="card-head" style="margin-bottom:20px">
                    <div>
                        <div class="card-title">{{ $editingId ? 'Ubah Perangkat' : 'Tambah Perangkat' }}</div>
                        <div class="card-sub">ID meter dibuat otomatis setelah disimpan dan dipakai gateway sebagai <span class="mono">meter_id</span></div>
                    </div>
                    <button type="button" class="btn btn-ghost" wire:click="$set('showForm', false)">Tutup</button>
                </div>


                <form wire:submit="save">
                    <div class="form-grid form-grid-2">
                        <div>
                            <div class="field">
                                <label class="field-label">Kode Meter <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="input mono @error('form.code') is-invalid @enderror" wire:model="form.code">
                                @error('form.code') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Nama Meter <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="input @error('form.name') is-invalid @enderror" wire:model="form.name">
                                @error('form.name') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="field">
                                <label class="field-label">Serial Number</label>
                                <input type="text" class="input mono" wire:model="form.serial_no">
                            </div>

                            <div class="field">
                                <label class="field-label">Merek</label>
                                <input type="text" class="input" wire:model="form.brand" placeholder="Schneider, Eastron, CHINT…">
                            </div>

                            <div class="field">
                                <label class="field-label">Model</label>
                                <input type="text" class="input" wire:model="form.model" placeholder="AW9L, SM630, DTS353…">
                            </div>
                        </div>

                        <div>
                            <div class="field">
                                <label class="field-label">Lokasi</label>
                                <input type="text" class="input" wire:model="form.location" placeholder="Panel Gudang B1">
                            </div>

                            <div class="field">
                                <label class="field-label">CT Ratio</label>
                                <input type="text" class="input mono" wire:model="form.ct_ratio" placeholder="800/5">
                            </div>

                            <div class="field">
                                <label class="field-label">Pengali kWh <span style="color:var(--danger)">*</span></label>
                                <input type="number" step="0.0001" min="0.0001"
                                       class="input mono @error('form.multiplier') is-invalid @enderror" wire:model="form.multiplier">
                                @error('form.multiplier') <div class="field-error">{{ $message }}</div> @enderror
                                <div class="card-sub">Isi 1 bila meter sudah mengirim nilai kWh sebenarnya.</div>
                            </div>

                            <div class="field">
                                <label class="field-label">Status</label>
                                <x-select-search wire:model="form.status"
                                    :options="[
                                        ['value' => 'active', 'label' => 'Aktif'],
                                        ['value' => 'maintenance', 'label' => 'Maintenance'],
                                        ['value' => 'inactive', 'label' => 'Nonaktif'],
                                    ]" />
                            </div>

                            <div class="field">
                                <label class="field-label">Tanggal Pemasangan</label>
                                <input type="date" class="input" wire:model="form.installed_at">
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
