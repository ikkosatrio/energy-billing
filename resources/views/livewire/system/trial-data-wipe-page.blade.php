<div>

    <div class="alert alert-danger mb-18">
        <strong>Khusus data uji coba.</strong> Berbeda dari hapus retensi di halaman Data Meter Mentah,
        di sini agregat harian (chart bulanan/tahunan) ikut terhapus permanen, bukan cuma pembacaan
        mentahnya. Jangan dipakai untuk membersihkan data yang sudah pernah ditagihkan ke pelanggan
        sungguhan.
    </div>

    {{-- ── Filter ──────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Power Meter</label>
                <x-select-search
                    wire:model.live="meterId"
                    search-placeholder="Cari kode, nama, atau lokasi meter…"
                    :options="$meters->map(fn ($m) => [
                        'value' => $m->id,
                        'label' => $m->code.' — '.$m->name,
                        'sub' => trim('ID meter '.$m->id.($m->location ? ' · '.$m->location : '')),
                    ])" />
            </div>
            <div class="field">
                <label class="field-label">Dari Tanggal</label>
                <input type="date" class="input mono" wire:model.live="from">
            </div>
            <div class="field">
                <label class="field-label">Sampai Tanggal</label>
                <input type="date" class="input mono" wire:model.live="to">
            </div>
        </div>
    </div>

    @if (!$meter)
        <div class="card"><div class="table-empty">Belum ada power meter terdaftar.</div></div>
    @elseif (!$rangeValid)
        <div class="alert alert-warning">"Sampai Tanggal" tidak boleh sebelum "Dari Tanggal".</div>
    @else
        {{-- ── Pratinjau yang akan terhapus ────────────────────────────── --}}
        <div class="stat-grid grid-1-1-1">
            <div class="card">
                <div class="stat-label">Pembacaan Mentah</div>
                <div class="stat-value sm" style="margin-top:8px">{{ number_format($preview['readings'], 0, ',', '.') }}</div>
                <div class="stat-foot">Baris di meter_readings pada rentang ini</div>
            </div>
            <div class="card">
                <div class="stat-label">Agregat Harian</div>
                <div class="stat-value sm" style="margin-top:8px">{{ number_format($preview['dailies'], 0, ',', '.') }}</div>
                <div class="stat-foot">Hari yang datanya dipakai chart bulanan/tahunan</div>
            </div>
            <div class="card">
                <div class="stat-label">Rentang Dipilih</div>
                <div class="stat-value sm" style="margin-top:8px;font-size:15px">
                    {{ \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y') }}
                    &nbsp;→&nbsp;
                    {{ \Illuminate\Support\Carbon::parse($to)->translatedFormat('d M Y') }}
                </div>
                <div class="stat-foot">{{ $meter->code }} — {{ $meter->name }}</div>
            </div>
        </div>

        @if ($overlaps->isNotEmpty())
            <div class="alert alert-warning mb-18">
                <strong>{{ $overlaps->count() }} invoice</strong> periodenya beririsan dengan rentang ini
                dan akan jadi tidak konsisten dengan agregat yang terhapus:
                <ul style="margin:8px 0 0 18px;padding:0">
                    @foreach ($overlaps as $inv)
                        <li>
                            {{ $inv->invoice_no }}
                            ({{ $inv->period_start->translatedFormat('d M Y') }}–{{ $inv->period_end->translatedFormat('d M Y') }})
                            — {{ \App\Models\Invoice::STATUS_LABELS[$inv->status] ?? $inv->status }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('reading.wipe_trial')
            @if ($preview['readings'] > 0 || $preview['dailies'] > 0)
                <div class="card">
                    <div class="row">
                        <button type="button" class="btn btn-danger"
                                x-on:click="ConfirmDialog.show({
                                        title: 'Hapus data uji coba sekarang?',
                                        text: '{{ number_format($preview['readings'], 0, ',', '.') }} pembacaan mentah dan {{ number_format($preview['dailies'], 0, ',', '.') }} agregat harian untuk {{ $meter->code }} pada {{ \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y') }}–{{ \Illuminate\Support\Carbon::parse($to)->translatedFormat('d M Y') }} akan dihapus PERMANEN, termasuk yang dipakai chart bulanan/tahunan. Tindakan ini tidak bisa dibatalkan.',
                                        danger: true,
                                        confirmText: 'Ya, Hapus Permanen',
                                        onConfirm: () => $wire.wipeNow(),
                                    })">
                            <i data-lucide="eraser" style="width:15px;height:15px"></i>
                            Hapus Data pada Rentang Ini
                        </button>
                    </div>
                </div>
            @else
                <div class="card"><div class="table-empty">Tidak ada data pada rentang ini untuk meter yang dipilih.</div></div>
            @endif
        @endcan
    @endif

</div>
