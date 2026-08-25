<div @if ($refreshEvery > 0) wire:poll.{{ $refreshEvery }}s="refresh" @endif>

    @php
        $fmtKwh = fn (float $value) => kwh($value);
        // Perkiraan rupiah disembunyikan dari akun tanpa hak lihat tagihan:
        // angka itu biaya energi saja (tanpa biaya beban, admin, PPJ, PPN),
        // jadi menampilkannya ke pihak yang memang tidak boleh melihat nominal
        // tagihan hanya membocorkan sebagian sekaligus salah dibaca sebagai
        // tagihan yang sah.
        $bisaLihatRupiah = auth('customer')->user()->hasPermission('portal.invoice.view');
    @endphp

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="filter-note">
                {{ $meters->count() }} power meter terpantau
            </div>
            <div class="spacer"></div>
            <div class="refresh-control">
                <div class="segmented" role="group" aria-label="Jeda penyegaran">
                    @foreach (\App\Livewire\Portal\MonitoringPage::REFRESH_OPTIONS as $seconds => $label)
                        <button type="button"
                                class="segmented-option {{ $refreshEvery === $seconds ? 'is-on' : '' }}"
                                @if ($refreshEvery === $seconds) aria-pressed="true" @endif
                                wire:click="$set('refreshEvery', {{ $seconds }})">{{ $label }}</button>
                    @endforeach
                    <span class="segmented-split" aria-hidden="true"></span>
                    <button type="button"
                            class="segmented-option {{ $refreshEvery === 0 ? 'is-on' : '' }}"
                            @if ($refreshEvery === 0) aria-pressed="true" @endif
                            wire:click="$set('refreshEvery', 0)">Manual</button>
                </div>
                <button type="button" class="btn-icon" wire:click="refresh"
                        wire:loading.class="is-busy" wire:target="refresh"
                        title="Segarkan sekarang" aria-label="Segarkan sekarang">
                    <i data-lucide="refresh-cw" style="width:16px;height:16px"></i>
                </button>
            </div>
        </div>
    </div>

    @if ($meters->isEmpty())
        <div class="card">
            <div class="table-empty">
                Belum ada power meter terpasang untuk data pelanggan Anda. Hubungi pengelola.
            </div>
        </div>
    @else
        <div class="meter-grid">
            @foreach ($meters as $index => $meter)
                @php
                    $card = $cards[$index];
                    $sum = $usage[$meter->id];
                    // Kondisi perangkat lebih baru bila gateway mengirim status
                    // lebih sering daripada pembacaan; dipakai lebih dulu.
                    $live = $meter->deviceStatus ?? $meter->latestReading;
                    $seenAt = $meter->deviceStatus?->read_at ?? $meter->latestReading?->read_at;
                    // Stand tidak selalu ikut pada payload status, jadi jatuh
                    // baliknya dicek terpisah dari $live.
                    $standLwbp = $meter->deviceStatus?->stand_lwbp ?? $meter->latestReading?->stand_lwbp;
                    $standWbp = $meter->deviceStatus?->stand_wbp ?? $meter->latestReading?->stand_wbp;
                    $lines = $meter->isSinglePhase() ? ['r' => 'R'] : ['r' => 'R', 's' => 'S', 't' => 'T'];
                @endphp

                <div class="card meter-card">

                    <div class="card-head" style="align-items:flex-start">
                        <div>
                            <div class="card-title">{{ $meter->name }}</div>
                            <div class="card-sub">
                                {{ $meter->customer?->name ?? '—' }} · {{ $meter->phase_label }}
                            </div>
                        </div>
                        <span class="badge {{ $card['badge'] }}">{{ $card['status'] }}</span>
                    </div>

                    {{-- ── Sekarang ─────────────────────────────────────── --}}
                    <div class="meter-section">
                        <div class="meter-section-label">Sekarang</div>

                        {{-- Stand register saat ini — angka yang selisihnya jadi
                             dasar tagihan, bukan daya sesaat. --}}
                        <div class="stand-row">
                            <div class="stand-cell">
                                <span class="stand-dot" style="background:var(--lwbp)"></span>
                                <div class="micro-label">Stand LWBP</div>
                                <div class="stand-value">
                                    {{ $standLwbp !== null ? $fmtKwh($standLwbp) : '—' }}<span class="stand-unit">kWh</span>
                                </div>
                            </div>
                            <div class="stand-cell">
                                <span class="stand-dot" style="background:var(--wbp)"></span>
                                <div class="micro-label">Stand WBP</div>
                                <div class="stand-value">
                                    {{ $standWbp !== null ? $fmtKwh($standWbp) : '—' }}<span class="stand-unit">kWh</span>
                                </div>
                            </div>
                        </div>

                        <div class="phase-table">
                            <div class="phase-row phase-head">
                                <span>Jalur</span>
                                <span>Tegangan</span>
                                <span>Arus</span>
                            </div>
                            @foreach ($lines as $key => $label)
                                <div class="phase-row">
                                    <span class="phase-tag">{{ $label }}</span>
                                    <span class="mono">
                                        {{ $live?->{'voltage_'.$key} !== null ? kwh($live->{'voltage_'.$key}).' V' : '—' }}
                                    </span>
                                    <span class="mono">
                                        {{ $live?->{'current_'.$key} !== null ? kwh($live->{'current_'.$key}).' A' : '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Akumulasi ────────────────────────────────────── --}}
                    <div class="meter-section">
                        <div class="meter-section-label">
                            Pemakaian
                            @if ($bisaLihatRupiah)
                                <span class="meter-section-note">estimasi biaya energi, di luar biaya beban &amp; pajak</span>
                            @endif
                        </div>

                        <div class="usage-list">
                            @foreach ([
                                ['key' => 'today', 'label' => 'Hari ini', 'note' => now()->translatedFormat('l, j M')],
                                ['key' => 'week', 'label' => 'Minggu ini', 'note' => 'sejak '.$sum['week_start']->translatedFormat('j M')],
                                ['key' => 'month', 'label' => 'Bulan ini', 'note' => 'sejak 1 '.now()->translatedFormat('M')],
                            ] as $span)
                                <div class="usage-row">
                                    <div class="usage-span">
                                        <div class="micro-label">{{ $span['label'] }}</div>
                                        <div class="usage-note">{{ $span['note'] }}</div>
                                    </div>
                                    <div class="usage-figures">
                                        <div class="usage-kwh">
                                            {{ $fmtKwh($sum[$span['key']]['kwh']) }}<span class="usage-unit">kWh</span>
                                        </div>
                                        @if ($bisaLihatRupiah)
                                            <div class="usage-rp">
                                                {{ $sum[$span['key']]['rp'] === null ? 'tarif belum diatur' : rupiah($sum[$span['key']]['rp']) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Bentuk bulan berjalan ────────────────────────── --}}
                    <div class="meter-section">
                        <div class="meter-section-label">Pemakaian harian · {{ $sum['span_label'] }}</div>

                        @if ($sum['max_kwh'] <= 0)
                            <div class="chart-empty">Belum ada pemakaian tercatat bulan ini.</div>
                        @else
                            <div class="day-chart" role="img"
                                 aria-label="Pemakaian harian {{ $sum['span_label'] }}, tertinggi {{ $fmtKwh($sum['peak']['kwh']) }} kWh pada {{ $sum['peak']['date']->translatedFormat('j F') }}">
                                @foreach ($sum['days'] as $day)
                                    <div class="day-bar-slot" title="{{ $day['date']->translatedFormat('D, j M') }} — {{ kwh($day['kwh']) }} kWh">
                                        <div class="day-bar {{ $day['is_peak'] ? 'is-peak' : '' }} {{ $day['is_today'] ? 'is-today' : '' }}"
                                             style="height:{{ max(2, round($day['kwh'] / $sum['max_kwh'] * 100)) }}%"></div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="peak-row">
                                <span class="peak-dot"></span>
                                <span class="peak-label">Tertinggi</span>
                                <span class="mono peak-value">{{ $fmtKwh($sum['peak']['kwh']) }} kWh</span>
                                <span class="peak-date">{{ $sum['peak']['date']->translatedFormat('l, j M') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="meter-foot">
                        <span>{{ $meter->code }}</span>
                        <span>{{ $seenAt ? 'Update '.$seenAt->diffForHumans() : 'Belum ada data' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
