{{-- Jeda penyegaran dipilih sendiri oleh pemakai; "Manual" mematikan polling. --}}
<div @if ($refreshEvery > 0) wire:poll.{{ $refreshEvery }}s="refresh" @endif>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field" style="min-width:200px">
                <label class="field-label">Jenis Sambungan</label>
                <x-select-search wire:model.live="phaseFilter"
                    :options="[
                        ['value' => '', 'label' => 'Semua jenis', 'sub' => $phaseCounts['all'].' perangkat'],
                        ['value' => '3', 'label' => '3 Phase', 'sub' => $phaseCounts['3'].' perangkat'],
                        ['value' => '1', 'label' => '1 Phase', 'sub' => $phaseCounts['1'].' perangkat'],
                    ]" />
            </div>
            <div class="filter-note">{{ $meters->count() }} perangkat ditampilkan</div>

            <div class="spacer"></div>

            <div class="field">
                <label class="field-label">Segarkan Otomatis</label>
                <div class="refresh-control">
                <div class="segmented" role="group" aria-label="Jeda penyegaran">
                    @foreach (\App\Livewire\Monitoring\RealtimePage::REFRESH_OPTIONS as $seconds => $label)
                        <button type="button"
                                class="segmented-option {{ $refreshEvery === $seconds ? 'is-on' : '' }}"
                                @if ($refreshEvery === $seconds) aria-pressed="true" @endif
                                wire:click="$set('refreshEvery', {{ $seconds }})">{{ $label }}</button>
                    @endforeach

                    {{-- Manual bukan bagian dari deret jeda, jadi dipisah garis. --}}
                    <span class="segmented-split" aria-hidden="true"></span>
                    <button type="button"
                            class="segmented-option {{ $refreshEvery === 0 ? 'is-on' : '' }}"
                            @if ($refreshEvery === 0) aria-pressed="true" @endif
                            wire:click="$set('refreshEvery', 0)">Manual</button>
                </div>

                {{-- Tetap tersedia walau jedanya panjang atau manual — kadang
                     angkanya ingin dilihat sekarang juga. --}}
                <button type="button" class="btn-icon" wire:click="refresh"
                        wire:loading.class="is-busy" wire:target="refresh"
                        title="Segarkan sekarang" aria-label="Segarkan sekarang">
                    <i data-lucide="refresh-cw" style="width:16px;height:16px"></i>
                </button>
                </div>
            </div>
        </div>
    </div>

    @if ($meters->isEmpty())
        <div class="card">
            <div class="table-empty">
                {{ $phaseFilter
                    ? 'Tidak ada perangkat '.($phaseFilter === '1' ? '1 phase' : '3 phase').' yang aktif.'
                    : 'Belum ada power meter aktif.' }}
            </div>
        </div>
    @else
        @php
            // Desimal hanya berguna selama angkanya kecil; pada puluhan ribu kWh
            // satu digit di belakang koma cuma memanjangkan baris.
            $fmtKwh = fn (float $value) => kwh($value, $value < 1000 ? 1 : 0);
        @endphp
        <div class="meter-grid">
            @foreach ($meters as $index => $meter)
                @php
                    $card = $cards[$index];
                    $sum = $usage[$meter->id];
                    // Kondisi perangkat lebih baru bila gateway mengirim status
                    // lebih sering daripada pembacaan; dipakai lebih dulu.
                    $live = $meter->deviceStatus ?? $meter->latestReading;
                    $seenAt = $meter->deviceStatus?->read_at ?? $meter->latestReading?->read_at;
                    // Stand tidak selalu ikut terkirim pada payload status —
                    // lihat DeviceStatusService — jadi jatuh baliknya dicek
                    // sendiri, terpisah dari $live yang dipakai untuk daya dan
                    // tegangan/arus.
                    $standLwbp = $meter->deviceStatus?->stand_lwbp ?? $meter->latestReading?->stand_lwbp;
                    $standWbp = $meter->deviceStatus?->stand_wbp ?? $meter->latestReading?->stand_wbp;
                    // Meter 1 phase hanya punya jalur R; kolom S dan T tidak
                    // ditampilkan agar tanda strip tidak salah dibaca sebagai
                    // data yang gagal terkirim.
                    $lines = $meter->isSinglePhase() ? ['r' => 'R'] : ['r' => 'R', 's' => 'S', 't' => 'T'];
                @endphp

                <div class="card meter-card">

                    <div class="card-head" style="align-items:flex-start">
                        <div>
                            <div class="card-title">{{ $meter->name }}</div>
                            <div class="card-sub">
                                {{ $meter->customer?->name ?? 'Belum terhubung' }} · {{ $meter->phase_label }}
                            </div>
                        </div>
                        <span class="badge {{ $card['badge'] }}">{{ $card['status'] }}</span>
                    </div>

                    {{-- ── Sekarang: berubah tiap kali gateway mengirim ──── --}}
                    <div class="meter-section">
                        <div class="meter-section-label">Sekarang</div>

                        {{-- Stand register saat ini — angka yang selisihnya jadi dasar
                             tagihan, bukan daya sesaat yang tidak dipakai menghitung. --}}
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
                                        {{ $live?->{'voltage_'.$key} !== null ? kwh($live->{'voltage_'.$key}, 1).' V' : '—' }}
                                    </span>
                                    <span class="mono">
                                        {{ $live?->{'current_'.$key} !== null ? kwh($live->{'current_'.$key}, 1).' A' : '—' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Akumulasi: berubah sekali sehari ──────────────── --}}
                    <div class="meter-section">
                        <div class="meter-section-label">
                            Pemakaian
                            <span class="meter-section-note">estimasi biaya energi, di luar biaya beban &amp; pajak</span>
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
                                        <div class="usage-rp">
                                            {{ $sum[$span['key']]['rp'] === null ? 'tarif belum diatur' : rupiah($sum[$span['key']]['rp']) }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Bentuk bulan berjalan, hari terboros ditandai ─── --}}
                    <div class="meter-section">
                        <div class="meter-section-label">Pemakaian harian · {{ $sum['span_label'] }}</div>

                        @if ($sum['max_kwh'] <= 0)
                            <div class="chart-empty">Belum ada pemakaian tercatat bulan ini.</div>
                        @else
                            <div class="day-chart" role="img"
                                 aria-label="Pemakaian harian {{ $sum['span_label'] }}, tertinggi {{ $fmtKwh($sum['peak']['kwh']) }} kWh pada {{ $sum['peak']['date']->translatedFormat('j F') }}">
                                @foreach ($sum['days'] as $day)
                                    <div class="day-bar-slot" title="{{ $day['date']->translatedFormat('D, j M') }} — {{ kwh($day['kwh'], 1) }} kWh">
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
                                @if ($sum['peak']['rp'] !== null)
                                    <span class="peak-rp mono">{{ rupiah($sum['peak']['rp']) }}</span>
                                @endif
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
