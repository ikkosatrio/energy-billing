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
            <div class="field" style="min-width:170px">
                <label class="field-label">Jenis Sambungan</label>
                <x-select-search wire:model.live="phaseFilter" placeholder="Semua jenis"
                    :options="[
                        ['value' => '', 'label' => 'Semua jenis'],
                        ['value' => '3', 'label' => '3 Phase'],
                        ['value' => '1', 'label' => '1 Phase'],
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

    {{-- <div class="alert alert-info mb-18">
        Kolom <strong>ID Meter</strong> di bawah adalah nilai yang dikirim gateway sebagai
        <span class="mono">meter_id</span> ke <span class="mono">{{ $ingestUrl }}</span>.
        Nilainya tetap dan bisa dilihat kapan saja.
        <a href="{{ $docsUrl }}" target="_blank">Buka dokumentasi API →</a>
    </div> --}}

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
                        <th>Sinyal</th>
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
                            <td class="text-muted">
                                {{ trim($meter->brand.' '.$meter->model) ?: '—' }}
                                <div class="sub">{{ $meter->phase_label }}</div>
                            </td>
                            <td class="text-muted">{{ $meter->location ?: '—' }}</td>
                            <td>{{ $meter->customer?->name ?? '— belum terhubung' }}</td>
                            <td class="num">{{ $meter->ct_ratio ?: '—' }}</td>
                            <td><x-signal-strength :status="$meter->deviceStatus" /></td>
                            <td class="num">
                                @if ($reading)
                                    <div class="stand-split">
                                        <span class="stand-split-item">
                                            <span class="legend-swatch lwbp"></span>LWBP {{ kwh($reading->stand_lwbp, 1) }}
                                        </span>
                                        <span class="stand-split-item">
                                            <span class="legend-swatch wbp"></span>WBP {{ kwh($reading->stand_wbp, 1) }}
                                        </span>
                                    </div>
                                    <div class="sub">kWh · {{ $reading->read_at->translatedFormat('d M H:i') }}</div>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td class="text-right nowrap">
                                @can('meter.update')
                                    <span class="link-action" wire:click="edit({{ $meter->id }})" style="margin-right:12px">Ubah</span>
                                @endcan
                                @can('meter.delete')
                                    <span class="link-action danger" x-on:click="ConfirmDialog.show({
                                            title: 'Hapus power meter ' + @js($meter->code) + '?',
                                            danger: true,
                                            confirmText: 'Ya, Hapus',
                                            onConfirm: () => $wire.delete({{ $meter->id }}),
                                        })">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="table-empty">
                                {{ $search || $statusFilter || $phaseFilter ? 'Tidak ada perangkat yang cocok dengan filter.' : 'Belum ada power meter. Tambahkan lewat tombol di atas.' }}
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

                            <div class="field">
                                <label class="field-label">Jenis Sambungan <span style="color:var(--danger)">*</span></label>
                                <x-select-search wire:model="form.phase"
                                    :options="[
                                        ['value' => '3', 'label' => '3 Phase', 'sub' => 'Tegangan & arus R, S, T'],
                                        ['value' => '1', 'label' => '1 Phase', 'sub' => 'Hanya jalur R'],
                                    ]" />
                                <div class="card-sub">
                                    Meter 1 phase menyembunyikan kolom S dan T di monitoring dan laporan,
                                    karena jalurnya memang tidak ada.
                                </div>
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
                                <label class="field-label">Angka Maksimum Register</label>
                                <input type="number" step="0.01" min="1"
                                       class="input mono @error('form.stand_max') is-invalid @enderror"
                                       placeholder="999999.99"
                                       wire:model="form.stand_max">
                                @error('form.stand_max') <div class="field-error">{{ $message }}</div> @enderror
                                <div class="card-sub">
                                    Angka tertinggi register sebelum berputar kembali ke nol —
                                    <span class="mono">999999.99</span> untuk register 6 digit.
                                    Isi apa adanya dari spesifikasi meter, sebelum dikali CT.
                                    Dikosongkan pun aman: stand yang mundur akan dianggap meter di-reset.
                                </div>
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

                    @if ($editingId)
                        {{-- Diisi perangkat lewat endpoint /status, tidak bisa diubah dari sini. --}}
                        <div class="device-panel" style="margin-top:18px">
                            <div class="device-panel-head">
                                <i data-lucide="cpu" style="width:14px;height:14px"></i>
                                Informasi Perangkat
                                <span style="margin-left:auto;text-transform:none;letter-spacing:0;font-weight:500">
                                    @if ($editingStatus?->updated_at)
                                        Diperbarui {{ $editingStatus->updated_at->diffForHumans() }}
                                    @endif
                                </span>
                            </div>

                            @if ($editingStatus)
                                <div class="device-grid">
                                    <div>
                                        <div class="device-item-label">Kekuatan Sinyal</div>
                                        <x-signal-strength :status="$editingStatus" />
                                    </div>
                                    <div>
                                        <div class="device-item-label">Alamat IP</div>
                                        <div class="device-item-value{{ $editingStatus->ip_address ? '' : ' empty' }}">
                                            {{ $editingStatus->ip_address ?? 'Belum dilaporkan' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="device-item-label">MAC Address</div>
                                        <div class="device-item-value{{ $editingStatus->mac_address ? '' : ' empty' }}">
                                            {{ $editingStatus->mac_address ?? 'Belum dilaporkan' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="device-item-label">Versi Firmware</div>
                                        <div class="device-item-value{{ $editingStatus->firmware_version ? '' : ' empty' }}">
                                            {{ $editingStatus->firmware_version ?? 'Belum dilaporkan' }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="device-item-value empty">
                                    Perangkat belum pernah mengirim kondisinya.
                                    Gateway mengirimnya ke <span class="mono">{{ url('/api/v1/status') }}</span>.
                                </div>
                            @endif
                        </div>
                    @endif

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
