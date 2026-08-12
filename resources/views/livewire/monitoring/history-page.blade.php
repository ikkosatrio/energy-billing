<div>

    @if ($meters->isEmpty())
        <div class="card"><div class="table-empty">Belum ada power meter aktif.</div></div>
    @else

        {{-- ── Filter ──────────────────────────────────────────────────── --}}
        <div class="card mb-18">
            <div class="filter-bar">
                <div class="field">
                    <label class="field-label">Power Meter</label>
                    <select class="select" wire:model.live="meterId">
                        @foreach ($meters as $option)
                            <option value="{{ $option->id }}">{{ $option->code }} — {{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Bulan</label>
                    <input type="month" class="input mono" wire:model.live="month">
                </div>
                <div class="field">
                    <label class="field-label">Tanggal (chart per jam)</label>
                    <input type="date" class="input mono" wire:model.live="day">
                </div>
            </div>
        </div>

        @php
            // Skala chart memakai nilai terbesar di masing-masing rentang,
            // sehingga bentuknya tetap terbaca berapa pun besaran angkanya.
            $hourlyMax = max(1, collect($hourly)->max(fn ($h) => $h['lwbp'] + $h['wbp']));
            $dailyMax  = max(1, $dailies->max(fn ($d) => $d->total_kwh) ?: 1);
            $monthMax  = max(1, collect($monthly)->max('total') ?: 1);
        @endphp

        {{-- ── Per jam ─────────────────────────────────────────────────── --}}
        <div class="card mb-18">
            <div class="card-head">
                <div>
                    <div class="card-title">Pemakaian Per Jam — {{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('d F Y') }}</div>
                    <div class="card-sub">kWh per jam, dihitung dari selisih stand meter</div>
                </div>
                <div class="chart-legend">
                    <span><span class="legend-swatch lwbp"></span>LWBP</span>
                    <span><span class="legend-swatch wbp"></span>WBP</span>
                </div>
            </div>

            <div class="bar-chart">
                @foreach ($hourly as $slot)
                    @php $total = $slot['lwbp'] + $slot['wbp']; @endphp
                    <div class="bar-col" title="{{ sprintf('%02d:00', $slot['hour']) }} — {{ kwh($total, 1) }} kWh">
                        <div class="bar-stack">
                            <div class="bar wbp" style="height:{{ $slot['wbp'] / $hourlyMax * 100 }}%"></div>
                            <div class="bar lwbp" style="height:{{ $slot['lwbp'] / $hourlyMax * 100 }}%"></div>
                        </div>
                        <div class="bar-label">{{ sprintf('%02d', $slot['hour']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── Per hari ────────────────────────────────────────────────── --}}
        <div class="card mb-18">
            <div class="card-head">
                <div>
                    <div class="card-title">Riwayat Per Hari — {{ $monthStart->translatedFormat('F Y') }}</div>
                    <div class="card-sub">Pemisahan LWBP / WBP tiap hari</div>
                </div>
                <div class="chart-legend">
                    <span><span class="legend-swatch lwbp"></span>LWBP</span>
                    <span><span class="legend-swatch wbp"></span>WBP</span>
                </div>
            </div>

            @if ($dailies->isEmpty())
                <div class="table-empty">Belum ada data agregat harian untuk bulan ini.</div>
            @else
                <div class="bar-chart" style="height:200px">
                    @foreach ($dailies as $daily)
                        <div class="bar-col" title="{{ $daily->date->translatedFormat('d M') }} — {{ kwh($daily->total_kwh, 1) }} kWh{{ $daily->is_incomplete ? ' (data tidak lengkap)' : '' }}">
                            <div class="bar-stack">
                                <div class="bar wbp" style="height:{{ $daily->kwh_wbp / $dailyMax * 100 }}%"></div>
                                <div class="bar lwbp" style="height:{{ $daily->kwh_lwbp / $dailyMax * 100 }}%;opacity:{{ $daily->is_incomplete ? .45 : 1 }}"></div>
                            </div>
                            <div class="bar-label">{{ $daily->date->day % 2 === 1 ? $daily->date->day : '' }}</div>
                        </div>
                    @endforeach
                </div>

                @if ($summary['incomplete_days'] > 0)
                    <div class="alert alert-warning" style="margin-top:14px">
                        {{ $summary['incomplete_days'] }} hari punya data tidak lengkap (gateway kemungkinan sempat offline).
                        Batang yang lebih pudar menandai hari tersebut.
                    </div>
                @endif
            @endif
        </div>

        <div class="grid grid-2">
            {{-- ── Per bulan ───────────────────────────────────────────── --}}
            <div class="card">
                <div class="card-title">Riwayat Per Bulan</div>
                <div class="card-sub" style="margin-bottom:18px">12 bulan terakhir, total kWh</div>

                <div class="bar-chart" style="height:180px;gap:8px">
                    @foreach ($monthly as $entry)
                        <div class="bar-col" title="{{ $entry['label'] }} — {{ kwh($entry['total'], 1) }} kWh">
                            <div class="bar primary" style="height:{{ $entry['total'] / $monthMax * 100 }}%"></div>
                            <div class="bar-label">{{ $entry['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Ringkasan ───────────────────────────────────────────── --}}
            <div class="card">
                <div class="card-title" style="margin-bottom:14px">Ringkasan Periode</div>

                <div class="kv-row">
                    <span class="kv-label">Total kWh {{ $monthStart->translatedFormat('F') }}</span>
                    <span class="kv-value">{{ kwh($summary['total'], 1) }}</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">LWBP</span>
                    <span class="kv-value">{{ kwh($summary['lwbp'], 1) }} kWh</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">WBP</span>
                    <span class="kv-value">{{ kwh($summary['wbp'], 1) }} kWh</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">Rata-rata harian</span>
                    <span class="kv-value">{{ kwh($summary['daily_average'], 1) }} kWh</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">Beban puncak</span>
                    <span class="kv-value">
                        @if ($summary['peak_kw'])
                            {{ kwh($summary['peak_kw'], 1) }} kW
                            <span class="text-faint" style="font-weight:500">
                                · {{ $summary['peak_at']?->translatedFormat('d M H:i') }}
                            </span>
                        @else
                            —
                        @endif
                    </span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">Load factor</span>
                    <span class="kv-value">
                        {{ $summary['load_factor'] !== null ? number_format($summary['load_factor'], 2, ',', '.') : '—' }}
                    </span>
                </div>
            </div>
        </div>

    @endif

</div>
