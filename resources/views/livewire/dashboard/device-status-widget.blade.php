{{-- Jeda penyegaran dipilih sendiri; "Manual" mematikan polling. Pola sama
     dengan Real-time Monitoring supaya satu kontrol dipelajari sekali. --}}
<div @if ($refreshEvery > 0) wire:poll.{{ $refreshEvery }}s="refresh" @endif class="card mb-18">

    <div class="device-widget-head">
        <div class="device-widget-title">
            <div>
                <div class="card-title">Real-time Perangkat</div>
                <div class="card-sub">{{ $meters->count() }} power meter aktif · kondisi live</div>
            </div>
            @if ($attentionCount > 0)
                <span class="badge badge-warning badge-square">{{ $attentionCount }} perlu perhatian</span>
            @endif
        </div>

        <div class="spacer"></div>

        <div class="segmented" role="group" aria-label="Jeda penyegaran">
            @foreach (\App\Livewire\Dashboard\DeviceStatusWidget::REFRESH_OPTIONS as $seconds => $label)
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

        @if (Route::has('monitoring.realtime') && auth()->user()->can('monitoring.view'))
            <a href="{{ route('monitoring.realtime') }}" wire:navigate class="link-action">Real-time penuh →</a>
        @endif
    </div>

    @if ($meters->isEmpty())
        <div class="table-empty">Belum ada power meter aktif.</div>
    @else
        @php
            // Desimal hanya berguna selama angkanya kecil; pada puluhan ribu kWh
            // satu digit di belakang koma cuma memanjangkan angka di kartu sempit.
            $fmtKwh = fn (float $value) => kwh($value, $value < 1000 ? 1 : 0);
        @endphp
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
                                {{ $meter->customer?->name ?? 'Belum terhubung' }} · {{ $meter->phase_label }}
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
                            <div class="device-tile-usage-rp">
                                {{ $sum['today']['rp'] === null ? '—' : rupiah($sum['today']['rp']) }}
                            </div>
                        </div>
                        <div class="device-tile-usage-item">
                            <div class="device-tile-usage-label">Bulan ini</div>
                            <div class="device-tile-usage-kwh">{{ $fmtKwh($sum['month']['kwh']) }} <small>kWh</small></div>
                            <div class="device-tile-usage-rp">
                                {{ $sum['month']['rp'] === null ? '—' : rupiah($sum['month']['rp']) }}
                            </div>
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
