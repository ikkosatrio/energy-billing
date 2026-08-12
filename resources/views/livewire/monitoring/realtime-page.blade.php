{{-- Disegarkan otomatis mengikuti interval push gateway. --}}
<div @if ($autoRefresh) wire:poll.{{ $pollInterval }}="refresh" @endif>

    <div class="card mb-18">
        <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div class="card-title">{{ $meters->count() }} perangkat dipantau</div>
                <div class="card-sub">
                    Gateway mengirim pembacaan tiap {{ setting('iot_push_interval_seconds', 60) }} detik;
                    meter dianggap offline setelah {{ \App\Models\PowerMeter::OFFLINE_AFTER_MINUTES }} menit tanpa data.
                </div>
            </div>
            <label class="checkbox-row" style="margin:0">
                <input type="checkbox" wire:model.live="autoRefresh">
                <span>Segarkan otomatis</span>
            </label>
        </div>
    </div>

    @if ($meters->isEmpty())
        <div class="card">
            <div class="table-empty">Belum ada power meter aktif.</div>
        </div>
    @else
        <div class="stat-grid grid-1-1-1">
            @foreach ($meters as $index => $meter)
                @php
                    $card = $cards[$index];
                    $reading = $meter->latestReading;
                @endphp
                <div class="card">
                    <div class="card-head" style="align-items:flex-start">
                        <div>
                            <div class="card-title">{{ $meter->name }}</div>
                            <div class="card-sub">
                                {{ $meter->customer?->name ?? 'Belum terhubung' }}
                                {{ $meter->location ? ' · '.$meter->location : '' }}
                            </div>
                        </div>
                        <span class="badge {{ $card['badge'] }}">{{ $card['status'] }}</span>
                    </div>

                    <div class="metric-grid">
                        <div class="metric">
                            <div class="metric-label">DAYA AKTIF</div>
                            <div class="metric-value">
                                {{ $reading?->active_power_kw !== null ? kwh($reading->active_power_kw, 1) : '—' }}
                                <small>kW</small>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">TEGANGAN</div>
                            <div class="metric-value">
                                {{ $reading?->voltage_r !== null ? kwh($reading->voltage_r, 1) : '—' }}
                                <small>V</small>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">ARUS</div>
                            <div class="metric-value">
                                {{ $reading?->current_r !== null ? kwh($reading->current_r, 1) : '—' }}
                                <small>A</small>
                            </div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">POWER FACTOR</div>
                            <div class="metric-value">
                                {{ $reading?->power_factor !== null ? number_format($reading->power_factor, 2, ',', '.') : '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="row" style="justify-content:space-between;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft);font-size:12px;color:var(--text-muted)">
                        <span>kWh hari ini <b class="mono" style="color:var(--text)">{{ kwh($todayUsage[$meter->id] ?? 0) }}</b></span>
                        <span>{{ $reading ? 'Update '.$reading->read_at->diffForHumans() : 'Belum ada data' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
