<div>

    @if ($meters->isEmpty())
        <div class="card">
            <div class="table-empty">
                Belum ada power meter terpasang untuk data pelanggan Anda. Hubungi pengelola.
            </div>
        </div>
    @else

        {{-- ── Filter ──────────────────────────────────────────────────── --}}
        <div class="card mb-18">
            <div class="filter-bar">
                @if ($meters->count() > 1)
                    <div class="field">
                        <label class="field-label">Power Meter</label>
                        <x-select-search
                            wire:model.live="meterId"
                            search-placeholder="Cari kode atau nama meter…"
                            :options="$meters->map(fn ($option) => [
                                'value' => $option->id,
                                'label' => $option->code.' — '.$option->name,
                            ])" />
                    </div>
                @endif
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
                    <div class="bar-col" title="{{ sprintf('%02d:00', $slot['hour']) }} — {{ kwh($total) }} kWh">
                        <div class="bar-stack">
                            @if ($total > 0)
                                <div class="bar-value">{{ kwh($total) }}</div>
                            @endif
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
                <div class="table-empty">Belum ada data pemakaian untuk bulan ini.</div>
            @else
                <div class="bar-chart" style="height:200px">
                    @foreach ($dailies as $daily)
                        <div class="bar-col" title="{{ $daily->date->translatedFormat('d M') }} — {{ kwh($daily->total_kwh) }} kWh">
                            <div class="bar-stack">
                                @if ($daily->total_kwh > 0)
                                    <div class="bar-value">{{ kwh($daily->total_kwh) }}</div>
                                @endif
                                <div class="bar wbp" style="height:{{ $daily->kwh_wbp / $dailyMax * 100 }}%"></div>
                                <div class="bar lwbp" style="height:{{ $daily->kwh_lwbp / $dailyMax * 100 }}%"></div>
                            </div>
                            <div class="bar-label">{{ $daily->date->day }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Angka pastinya — tinggi batang tidak terbaca sebagai nilai
                     tanpa mengarahkan mouse satu per satu. --}}
                <div class="table-wrap" style="margin-top:16px">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="num">LWBP (kWh)</th>
                                <th class="num">WBP (kWh)</th>
                                <th class="num">Total (kWh)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dailies as $daily)
                                <tr>
                                    <td class="mono">{{ $daily->date->translatedFormat('d M Y (D)') }}</td>
                                    <td class="num">{{ kwh($daily->kwh_lwbp) }}</td>
                                    <td class="num">{{ kwh($daily->kwh_wbp) }}</td>
                                    <td class="num strong">{{ kwh($daily->total_kwh) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:var(--bg-subtle);font-weight:700">
                                <td>Total {{ $monthStart->translatedFormat('F') }}</td>
                                <td class="num">{{ kwh($summary['lwbp']) }}</td>
                                <td class="num">{{ kwh($summary['wbp']) }}</td>
                                <td class="num">{{ kwh($summary['total']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>

        <div class="grid grid-2">
            {{-- ── Per bulan ───────────────────────────────────────────── --}}
            <div class="card">
                <div class="card-title">Riwayat Per Bulan</div>
                <div class="card-sub" style="margin-bottom:18px">12 bulan terakhir, total kWh</div>

                <div class="bar-chart" style="height:180px;gap:8px">
                    @foreach ($monthly as $entry)
                        <div class="bar-col" title="{{ $entry['label'] }} — {{ kwh($entry['total']) }} kWh">
                            <div class="bar-stack">
                                @if ($entry['total'] > 0)
                                    <div class="bar-value">{{ kwh($entry['total']) }}</div>
                                @endif
                                <div class="bar primary" style="height:{{ $entry['total'] / $monthMax * 100 }}%"></div>
                            </div>
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
                    <span class="kv-value">{{ kwh($summary['total']) }}</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">LWBP</span>
                    <span class="kv-value">{{ kwh($summary['lwbp']) }} kWh</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">WBP</span>
                    <span class="kv-value">{{ kwh($summary['wbp']) }} kWh</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">Rata-rata harian</span>
                    <span class="kv-value">{{ kwh($summary['daily_average']) }} kWh</span>
                </div>
                <div class="kv-row">
                    <span class="kv-label">Beban puncak</span>
                    <span class="kv-value">
                        @if ($summary['peak_kw'])
                            {{ kwh($summary['peak_kw']) }} kW
                            <span class="text-faint" style="font-weight:500">
                                · {{ $summary['peak_at']?->translatedFormat('d M H:i') }}
                            </span>
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>
        </div>

    @endif

</div>
