<div>

    @if ($meters->isEmpty())
        <div class="card"><div class="table-empty">Belum ada power meter terdaftar.</div></div>
    @else

        {{-- ── Filter ──────────────────────────────────────────────────── --}}
        <div class="card mb-18">
            <div class="filter-bar">
                <div class="field">
                    <label class="field-label">Power Meter</label>
                    <x-select-search
                        wire:model.live="meterId"
                        search-placeholder="Cari kode, nama, atau lokasi meter…"
                        :options="$meters->map(fn ($meter) => [
                            'value' => $meter->id,
                            'label' => $meter->code.' — '.$meter->name,
                            'sub' => trim('ID meter '.$meter->id.($meter->location ? ' · '.$meter->location : '')),
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
                <div class="field" style="min-width:120px">
                    <label class="field-label">Baris</label>
                    <x-select-search wire:model.live="perPage"
                        :options="[
                            ['value' => 50, 'label' => '50'],
                            ['value' => 100, 'label' => '100'],
                            ['value' => 250, 'label' => '250'],
                        ]" />
                </div>
                <div class="spacer"></div>
                @can('report.export')
                    <a href="{{ route('report.export', ['type' => 'readings', 'format' => 'xlsx']) }}?{{ $exportQuery }}"
                       class="btn btn-outline">
                        <i data-lucide="sheet" style="width:15px;height:15px"></i> Excel
                    </a>
                @endcan
            </div>

            <label class="checkbox-row" style="margin-top:4px">
                <input type="checkbox" wire:model.live="onlyAnomalies">
                <span>Hanya tampilkan baris bermasalah (stand mundur atau jeda data)</span>
            </label>
        </div>

        {{-- ── Ringkasan rentang ───────────────────────────────────────── --}}
        <div class="stat-grid grid-1-1-1">
            <div class="card">
                <div class="stat-label">Jumlah Pembacaan</div>
                <div class="stat-value sm" style="margin-top:8px">{{ number_format($summary['count'], 0, ',', '.') }}</div>
                <div class="stat-foot {{ $summary['completeness'] < 90 ? 'down' : 'up' }}">
                    {{ $summary['completeness'] }}% dari {{ number_format($summary['expected'], 0, ',', '.') }} yang diharapkan
                </div>
            </div>
            <div class="card">
                <div class="stat-label">Rentang Data</div>
                <div class="stat-value sm" style="margin-top:8px;font-size:15px">
                    {{ $summary['first_at']?->translatedFormat('d M H:i') ?? '—' }}
                    &nbsp;→&nbsp;
                    {{ $summary['last_at']?->translatedFormat('d M H:i') ?? '—' }}
                </div>
                <div class="stat-foot">Pembacaan pertama dan terakhir</div>
            </div>
            <div class="card">
                <div class="stat-label">Selisih Stand</div>
                <div class="stat-value sm" style="margin-top:8px">
                    {{ kwh($summary['kwh_lwbp'] + $summary['kwh_wbp'], 1) }} <small>kWh</small>
                </div>
                <div class="stat-foot">
                    LWBP {{ kwh($summary['kwh_lwbp'], 1) }} · WBP {{ kwh($summary['kwh_wbp'], 1) }}
                </div>
            </div>
        </div>

        @if ($summary['completeness'] < 90 && $summary['count'] > 0)
            <div class="alert alert-warning mb-18">
                Data pada rentang ini tidak lengkap — hanya {{ $summary['completeness'] }}% dari yang
                seharusnya masuk. Tagihan tetap benar karena dihitung dari selisih stand awal & akhir,
                bukan penjumlahan tiap pembacaan.
            </div>
        @endif

        {{-- ── Tabel ───────────────────────────────────────────────────── --}}
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu Baca</th>
                            <th class="num">Stand LWBP</th>
                            <th class="num">Δ LWBP</th>
                            <th class="num">Stand WBP</th>
                            <th class="num">Δ WBP</th>
                            <th class="num">Daya (kW)</th>
                            <th class="num">Tegangan R</th>
                            <th class="num">Arus R</th>
                            <th class="num">PF</th>
                            <th>Sumber</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $r = $row['reading']; @endphp
                            <tr @if ($row['is_anomaly']) style="background:var(--danger-bg)" @endif>
                                <td class="mono nowrap">{{ $r->read_at->translatedFormat('d M Y H:i:s') }}</td>
                                <td class="num">{{ kwh($r->stand_lwbp, 2) }}</td>
                                <td class="num {{ ($row['delta_lwbp'] ?? 0) < 0 ? '' : 'text-muted' }}"
                                    @if (($row['delta_lwbp'] ?? 0) < 0) style="color:var(--danger);font-weight:600" @endif>
                                    {{ $row['delta_lwbp'] === null ? '—' : kwh($row['delta_lwbp'], 2) }}
                                </td>
                                <td class="num">{{ kwh($r->stand_wbp, 2) }}</td>
                                <td class="num {{ ($row['delta_wbp'] ?? 0) < 0 ? '' : 'text-muted' }}"
                                    @if (($row['delta_wbp'] ?? 0) < 0) style="color:var(--danger);font-weight:600" @endif>
                                    {{ $row['delta_wbp'] === null ? '—' : kwh($row['delta_wbp'], 2) }}
                                </td>
                                <td class="num">{{ $r->active_power_kw !== null ? kwh($r->active_power_kw, 1) : '—' }}</td>
                                <td class="num">{{ $r->voltage_r !== null ? kwh($r->voltage_r, 1) : '—' }}</td>
                                <td class="num">{{ $r->current_r !== null ? kwh($r->current_r, 1) : '—' }}</td>
                                <td class="num">{{ $r->power_factor !== null ? number_format($r->power_factor, 2, ',', '.') : '—' }}</td>
                                <td>
                                    <span class="badge {{ $r->source === 'api' ? 'badge-info' : 'badge-neutral' }}">
                                        {{ $r->source === 'api' ? 'Gateway' : 'Manual' }}
                                    </span>
                                </td>
                                <td class="nowrap">
                                    @if ($row['stand_dropped'])
                                        <span class="badge badge-danger">Stand mundur</span>
                                    @elseif ($row['has_gap'])
                                        <span class="badge badge-warning">
                                            Jeda {{ intdiv($row['gap_seconds'], 60) }} mnt
                                        </span>
                                    @else
                                        <span class="text-faint">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="table-empty">
                                    {{ $onlyAnomalies
                                        ? 'Tidak ada baris bermasalah pada halaman ini.'
                                        : 'Belum ada pembacaan pada rentang tanggal ini.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($readings && $readings->hasPages())
                <div style="margin-top:16px">{{ $readings->links() }}</div>
            @endif

            @if ($onlyAnomalies)
                <div class="card-sub" style="margin-top:12px">
                    Penyaring anomali bekerja per halaman — halaman lain bisa berisi baris bermasalah
                    yang belum terlihat. Untuk pemeriksaan menyeluruh, gunakan export Excel.
                </div>
            @endif
        </div>

    @endif

</div>
