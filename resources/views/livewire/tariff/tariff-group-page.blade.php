<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="Kode atau nama golongan…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="spacer"></div>
            @can('tariff.create')
                <button type="button" class="btn btn-primary" wire:click="createGroup">
                    <i data-lucide="plus" style="width:15px;height:15px"></i>
                    Tambah Golongan
                </button>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <div>
                <div class="card-title">Tarif Berlaku</div>
                <div class="card-sub">
                    Mengubah tarif berarti menerbitkan tarif baru — tarif lama ditutup, tidak ditimpa,
                    sehingga invoice periode sebelumnya tetap konsisten.
                </div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Golongan</th>
                        <th class="num">LWBP /kWh</th>
                        <th class="num">WBP /kWh</th>
                        <th class="num">Beban /kVA</th>
                        <th>Berlaku Sejak</th>
                        <th class="num">Pelanggan</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        @php $rate = $currentRates[$group->id] ?? null; @endphp
                        <tr>
                            <td class="strong">
                                {{ $group->code }}
                                <div class="sub">{{ $group->name }}</div>
                            </td>
                            <td class="num">{{ $rate ? rupiah($rate->rate_lwbp) : '—' }}</td>
                            <td class="num">{{ $rate ? rupiah($rate->rate_wbp) : '—' }}</td>
                            <td class="num">{{ $rate ? rupiah($rate->rate_beban_per_kva) : '—' }}</td>
                            <td class="text-muted">
                                {{ $rate ? $rate->effective_from->translatedFormat('d M Y') : 'Belum ada tarif' }}
                            </td>
                            <td class="num">{{ $group->customers_count }}</td>
                            <td>
                                <span class="badge {{ $group->is_active ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $group->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-right nowrap">
                                <span class="link-action muted" wire:click="showHistory({{ $group->id }})" style="margin-right:12px">Riwayat</span>
                                @can('tariff.create')
                                    <span class="link-action" wire:click="newRate({{ $group->id }})" style="margin-right:12px">Tarif Baru</span>
                                @endcan
                                @can('tariff.update')
                                    <span class="link-action muted" wire:click="editGroup({{ $group->id }})" style="margin-right:12px">Ubah</span>
                                @endcan
                                @can('tariff.delete')
                                    <span class="link-action danger"
                                          wire:click="deleteGroup({{ $group->id }})"
                                          wire:confirm="Hapus golongan {{ $group->code }} beserta seluruh riwayat tarifnya?">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty">Belum ada golongan tarif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Form golongan ───────────────────────────────────────────────── --}}
    @if ($showGroupForm)
        <div class="modal-overlay" wire:click.self="$set('showGroupForm', false)">
            <div class="modal modal-sm">
                <div class="card-title" style="margin-bottom:20px">
                    {{ $editingGroupId ? 'Ubah Golongan Tarif' : 'Tambah Golongan Tarif' }}
                </div>

                <form wire:submit="saveGroup">
                    <div class="field">
                        <label class="field-label">Kode <span style="color:var(--danger)">*</span></label>
                        <input type="text" class="input @error('groupForm.code') is-invalid @enderror"
                               placeholder="I-3/TR" wire:model="groupForm.code">
                        @error('groupForm.code') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Nama <span style="color:var(--danger)">*</span></label>
                        <input type="text" class="input @error('groupForm.name') is-invalid @enderror"
                               placeholder="I-3 / Tegangan Rendah" wire:model="groupForm.name">
                        @error('groupForm.name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Keterangan</label>
                        <input type="text" class="input" wire:model="groupForm.description">
                    </div>

                    <label class="checkbox-row">
                        <input type="checkbox" wire:model="groupForm.is_active">
                        <span>Golongan aktif</span>
                    </label>

                    <div class="row" style="margin-top:24px">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showGroupForm', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── Form tarif baru ─────────────────────────────────────────────── --}}
    @if ($showRateForm)
        <div class="modal-overlay" wire:click.self="$set('showRateForm', false)">
            <div class="modal modal-sm">
                <div class="card-title">Terbitkan Tarif Baru</div>
                <div class="card-sub" style="margin-bottom:20px">
                    Tarif yang sedang berjalan akan ditutup sehari sebelum tanggal mulai di bawah.
                </div>

                <form wire:submit="saveRate">
                    <div class="field">
                        <label class="field-label">Berlaku Mulai <span style="color:var(--danger)">*</span></label>
                        <input type="date" class="input @error('rateForm.effective_from') is-invalid @enderror"
                               wire:model="rateForm.effective_from">
                        @error('rateForm.effective_from') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Tarif LWBP per kWh <span style="color:var(--danger)">*</span></label>
                        <input type="number" step="0.01" min="0"
                               class="input mono @error('rateForm.rate_lwbp') is-invalid @enderror" wire:model="rateForm.rate_lwbp">
                        @error('rateForm.rate_lwbp') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Tarif WBP per kWh <span style="color:var(--danger)">*</span></label>
                        <input type="number" step="0.01" min="0"
                               class="input mono @error('rateForm.rate_wbp') is-invalid @enderror" wire:model="rateForm.rate_wbp">
                        @error('rateForm.rate_wbp') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Biaya Beban per kVA</label>
                        <input type="number" step="0.01" min="0" class="input mono" wire:model="rateForm.rate_beban_per_kva">
                        <div class="card-sub">Hanya terpakai oleh pelanggan bermode biaya beban “per kVA”.</div>
                    </div>

                    <div class="field">
                        <label class="field-label">Catatan</label>
                        <input type="text" class="input" wire:model="rateForm.notes"
                               placeholder="mis. penyesuaian tarif adjustment triwulan">
                    </div>

                    <div class="row" style="margin-top:24px">
                        <button type="submit" class="btn btn-primary">Terbitkan</button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showRateForm', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── Riwayat tarif ───────────────────────────────────────────────── --}}
    @if ($historyGroup)
        <div class="modal-overlay" wire:click.self="$set('historyGroupId', null)">
            <div class="modal">
                <div class="card-head" style="margin-bottom:20px">
                    <div>
                        <div class="card-title">Riwayat Tarif — {{ $historyGroup->code }}</div>
                        <div class="card-sub">{{ $historyGroup->name }}</div>
                    </div>
                    <button type="button" class="btn btn-ghost" wire:click="$set('historyGroupId', null)">Tutup</button>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Periode Berlaku</th>
                                <th class="num">LWBP</th>
                                <th class="num">WBP</th>
                                <th class="num">Beban /kVA</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $rate)
                                <tr>
                                    <td>
                                        {{ $rate->effective_from->translatedFormat('d M Y') }} –
                                        {{ $rate->effective_to?->translatedFormat('d M Y') ?? 'sekarang' }}
                                        @unless ($rate->effective_to)
                                            <span class="badge badge-success" style="margin-left:8px">Berjalan</span>
                                        @endunless
                                    </td>
                                    <td class="num">{{ rupiah($rate->rate_lwbp) }}</td>
                                    <td class="num">{{ rupiah($rate->rate_wbp) }}</td>
                                    <td class="num">{{ rupiah($rate->rate_beban_per_kva) }}</td>
                                    <td class="text-muted">{{ $rate->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="table-empty">Belum ada tarif untuk golongan ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
