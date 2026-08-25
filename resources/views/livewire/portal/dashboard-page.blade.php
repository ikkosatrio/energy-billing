{{-- Jeda penyegaran dipilih sendiri; "Manual" mematikan polling. --}}
<div @if ($refreshEvery > 0) wire:poll.{{ $refreshEvery }}s="refresh" @endif>

    @php
        // Desimal hanya berguna selama angkanya kecil; pada puluhan ribu kWh
        // satu digit di belakang koma cuma memanjangkan angka di kartu sempit.
        $fmtKwh = fn (float $value) => kwh($value, $value < 1000 ? 1 : 0);
        $bisaLihatTagihan = auth('customer')->user()->hasPermission('portal.invoice.view');
    @endphp

    {{-- ── Ringkasan ───────────────────────────────────────────────── --}}
    <div class="stat-grid {{ $bisaLihatTagihan ? 'grid-1-1-1' : 'grid-1-1-1' }}">
        <div class="card">
            <div class="stat-label">Pemakaian Hari Ini</div>
            <div class="stat-value sm" style="margin-top:8px">
                {{ $fmtKwh($totalHariIni) }} <small>kWh</small>
            </div>
            <div class="stat-foot">{{ now()->translatedFormat('l, j F Y') }}</div>
        </div>

        <div class="card">
            <div class="stat-label">Pemakaian Bulan Ini</div>
            <div class="stat-value sm" style="margin-top:8px">
                {{ $fmtKwh($totalBulanIni) }} <small>kWh</small>
            </div>
            <div class="stat-foot">Sejak 1 {{ now()->translatedFormat('F') }}</div>
        </div>

        @if ($bisaLihatTagihan)
            <div class="card">
                <div class="stat-label">Tagihan Belum Lunas</div>
                <div class="stat-value sm" style="margin-top:8px">{{ rupiah($tagihan['outstanding']) }}</div>
                <div class="stat-foot {{ $tagihan['overdue_count'] > 0 ? 'down' : '' }}">
                    @if ($tagihan['unpaid_count'] === 0)
                        Semua tagihan sudah lunas
                    @elseif ($tagihan['overdue_count'] > 0)
                        {{ $tagihan['unpaid_count'] }} invoice · {{ $tagihan['overdue_count'] }} lewat jatuh tempo
                    @else
                        {{ $tagihan['unpaid_count'] }} invoice menunggu pembayaran
                    @endif
                </div>
            </div>
        @else
            <div class="card">
                <div class="stat-label">Meter Terpantau</div>
                <div class="stat-value sm" style="margin-top:8px">{{ $meters->count() }} <small>unit</small></div>
                <div class="stat-foot {{ $offlineCount > 0 ? 'down' : 'up' }}">
                    {{ $offlineCount > 0 ? $offlineCount.' sedang tidak mengirim data' : 'Semua mengirim data normal' }}
                </div>
            </div>
        @endif
    </div>

    @if ($offlineCount > 0 && $bisaLihatTagihan)
        <div class="alert alert-warning mb-18">
            {{ $offlineCount }} dari {{ $meters->count() }} meter Anda sedang tidak mengirim data.
            Angka pemakaian hari ini bisa lebih rendah dari kenyataan sampai perangkatnya terhubung kembali.
        </div>
    @endif

    {{-- ── Kartu perangkat ─────────────────────────────────────────── --}}
    <div class="card">
        <div class="device-widget-head">
            <div class="device-widget-title">
                <div>
                    <div class="card-title">Kondisi Meter</div>
                    <div class="card-sub">{{ $meters->count() }} power meter · kondisi live</div>
                </div>
            </div>

            <div class="spacer"></div>

            <div class="segmented" role="group" aria-label="Jeda penyegaran">
                @foreach (\App\Livewire\Portal\DashboardPage::REFRESH_OPTIONS as $seconds => $label)
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

        @if ($meters->isEmpty())
            <div class="table-empty">
                Belum ada power meter terpasang untuk data pelanggan Anda. Hubungi pengelola.
            </div>
        @else
            <div class="device-grid-widget">
                @foreach ($meters as $index => $meter)
                    @php
                        $card = $cards[$index];
                        $sum = $usage[$meter->id];
                        $live = $meter->deviceStatus ?? $meter->latestReading;
                        $seenAt = $meter->deviceStatus?->read_at ?? $meter->latestReading?->read_at;
                        $lines = $meter->isSinglePhase() ? ['r' => 'R'] : ['r' => 'R', 's' => 'S', 't' => 'T'];
                        $tone = match ($card['badge']) {
                            'badge-danger' => 'is-danger',
                            'badge-warning' => 'is-warning',
                            default => '',
                        };
                    @endphp

                    <div class="device-tile {{ $tone }}">
                        <div class="device-tile-top">
                            <div style="min-width:0">
                                <div class="device-tile-name">{{ $meter->name }}</div>
                                <div class="device-tile-sub">
                                    {{ $meter->customer?->name ?? '—' }} · {{ $meter->phase_label }}
                                </div>
                            </div>
                            <span class="badge {{ $card['badge'] }} badge-square">{{ $card['status'] }}</span>
                        </div>

                        <div class="device-tile-power">
                            <span class="device-tile-kw">
                                {{ $live?->active_power_kw !== null ? kwh($live->active_power_kw, 1) : '—' }}<small>kW</small>
                            </span>
                            <span class="device-tile-pf">
                                PF {{ $live?->power_factor !== null ? number_format($live->power_factor, 2, ',', '.') : '—' }}
                            </span>
                        </div>

                        <div class="device-tile-phases">
                            @foreach ($lines as $key => $label)
                                <div class="device-tile-phase-row">
                                    <b>{{ $label }}</b>
                                    <span>{{ $live?->{'voltage_'.$key} !== null ? kwh($live->{'voltage_'.$key}, 0).'V' : '—' }}</span>
                                    <span>{{ $live?->{'current_'.$key} !== null ? kwh($live->{'current_'.$key}, 1).'A' : '—' }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="device-tile-usage">
                            <div class="device-tile-usage-item">
                                <div class="device-tile-usage-label">Hari ini</div>
                                <div class="device-tile-usage-kwh">{{ $fmtKwh($sum['today']['kwh']) }} <small>kWh</small></div>
                            </div>
                            <div class="device-tile-usage-item">
                                <div class="device-tile-usage-label">Bulan ini</div>
                                <div class="device-tile-usage-kwh">{{ $fmtKwh($sum['month']['kwh']) }} <small>kWh</small></div>
                            </div>
                        </div>

                        <div class="device-tile-foot">
                            <span class="mono">{{ $meter->code }}</span>
                            <span>{{ $seenAt ? $seenAt->diffForHumans() : 'belum ada data' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
