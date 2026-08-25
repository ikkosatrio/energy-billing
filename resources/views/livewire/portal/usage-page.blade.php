<div>

    {{-- ── Filter ──────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
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

    @if (!$rangeValid)
        <div class="alert alert-warning">"Sampai Tanggal" tidak boleh sebelum "Dari Tanggal".</div>
    @else

        {{-- ── Ringkasan ───────────────────────────────────────────── --}}
        <div class="stat-grid grid-1-1-1">
            <div class="card">
                <div class="stat-label">Total Pemakaian</div>
                <div class="stat-value sm" style="margin-top:8px">
                    {{ kwh($totals['total_kwh'], 1) }} <small>kWh</small>
                </div>
                <div class="stat-split">
                    <span class="stat-split-item">
                        <span class="legend-swatch lwbp"></span>
                        LWBP <strong>{{ kwh($totals['lwbp'], 1) }}</strong>
                    </span>
                    <span class="stat-split-item">
                        <span class="legend-swatch wbp"></span>
                        WBP <strong>{{ kwh($totals['wbp'], 1) }}</strong>
                    </span>
                </div>
            </div>
            <div class="card">
                <div class="stat-label">Rentang</div>
                <div class="stat-value sm" style="margin-top:8px;font-size:15px">
                    {{ \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y') }}
                    &nbsp;→&nbsp;
                    {{ \Illuminate\Support\Carbon::parse($to)->translatedFormat('d M Y') }}
                </div>
                <div class="stat-foot">
                    {{ \Illuminate\Support\Carbon::parse($from)->diffInDays(\Illuminate\Support\Carbon::parse($to)) + 1 }} hari
                </div>
            </div>
            <div class="card">
                <div class="stat-label">Lokasi</div>
                <div class="stat-value sm" style="margin-top:8px">{{ $rows->count() }} <small>pelanggan</small></div>
                <div class="stat-foot">Sesuai data yang bisa Anda akses</div>
            </div>
        </div>

        {{-- ── Tabel ───────────────────────────────────────────────── --}}
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>Meter</th>
                            <th class="num">LWBP (kWh)</th>
                            <th class="num">WBP (kWh)</th>
                            <th class="num">Total (kWh)</th>
                            <th class="num">Beban Puncak (kW)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="strong">{{ $row['customer'] }}</td>
                                <td class="mono text-muted">{{ $row['meter'] }}</td>
                                <td class="num">{{ kwh($row['lwbp'], 1) }}</td>
                                <td class="num">{{ kwh($row['wbp'], 1) }}</td>
                                <td class="num strong">{{ kwh($row['total_kwh'], 1) }}</td>
                                <td class="num">{{ $row['peak_kw'] !== null ? kwh($row['peak_kw'], 1) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="table-empty">
                                    Belum ada data pelanggan yang bisa Anda akses. Hubungi pengelola.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->count() > 1)
                        <tfoot>
                            <tr style="background:var(--bg-subtle);font-weight:700">
                                <td colspan="2">Total</td>
                                <td class="num">{{ kwh($totals['lwbp'], 1) }}</td>
                                <td class="num">{{ kwh($totals['wbp'], 1) }}</td>
                                <td class="num">{{ kwh($totals['total_kwh'], 1) }}</td>
                                <td class="num">—</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div class="card-sub" style="margin-top:12px">
                Angka dihitung dari selisih stand meter, sudah memperhitungkan penggantian
                atau reset meter di tengah periode.
            </div>
        </div>

    @endif

</div>
