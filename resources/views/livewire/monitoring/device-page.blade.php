<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Status Koneksi</label>
                <x-select-search wire:model.live="connectionFilter" placeholder="Semua"
                    :options="[
                        ['value' => '', 'label' => 'Semua'],
                        ['value' => 'online', 'label' => 'Online'],
                        ['value' => 'offline', 'label' => 'Offline'],
                        ['value' => 'maintenance', 'label' => 'Maintenance'],
                    ]" />
            </div>
            <div class="spacer"></div>
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Perangkat</th>
                        <th>Pelanggan</th>
                        <th>Lokasi</th>
                        <th>Koneksi</th>
                        <th>Sinyal</th>
                        <th>Alamat IP</th>
                        <th>Firmware</th>
                        <th>Terakhir Kirim</th>
                        <th class="num">LWBP (kWh)</th>
                        <th class="num">WBP (kWh)</th>
                        <th class="num">Total (kWh)</th>
                        <th class="num">Stand LWBP</th>
                        <th class="num">Stand WBP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($meters as $meter)
                        @php
                            $status = $meter->connection_status;
                            $badge = match ($status) {
                                'online' => ['Online', 'badge-success'],
                                'maintenance' => ['Maintenance', 'badge-warning'],
                                default => ['Offline', 'badge-danger'],
                            };
                            $usage = $todayUsage[$meter->id] ?? ['lwbp' => 0.0, 'wbp' => 0.0];
                        @endphp
                        <tr>
                            <td class="strong">
                                {{ $meter->name }}
                                <div class="sub mono">{{ $meter->code }} · {{ $meter->phase_label }}</div>
                            </td>
                            <td>{{ $meter->customer?->name ?? '— belum terhubung' }}</td>
                            <td class="text-muted">{{ $meter->location ?: '—' }}</td>
                            <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td><x-signal-strength :status="$meter->deviceStatus" /></td>
                            <td class="mono text-muted">{{ $meter->deviceStatus?->ip_address ?? '—' }}</td>
                            <td class="mono text-muted">
                                {{ $meter->deviceStatus?->firmware_version ?? '—' }}
                                @if ($meter->deviceStatus?->mac_address)
                                    <div class="sub mono">{{ $meter->deviceStatus->mac_address }}</div>
                                @endif
                            </td>
                            <td class="text-muted">
                                @if ($meter->last_seen_at)
                                    {{ $meter->last_seen_at->diffForHumans() }}
                                    <div class="sub mono">{{ $meter->last_seen_at->translatedFormat('d M Y H:i') }}</div>
                                @else
                                    Belum pernah
                                @endif
                            </td>
                            <td class="num">
                                <span class="stand-split-item" style="justify-content:flex-end">
                                    <span class="legend-swatch lwbp"></span>{{ kwh($usage['lwbp'], 1) }}
                                </span>
                            </td>
                            <td class="num">
                                <span class="stand-split-item" style="justify-content:flex-end">
                                    <span class="legend-swatch wbp"></span>{{ kwh($usage['wbp'], 1) }}
                                </span>
                            </td>
                            <td class="num strong">{{ kwh($usage['lwbp'] + $usage['wbp'], 1) }}</td>
                            {{-- Dua register terpisah, bukan dijumlahkan — LWBP dan WBP
                                 adalah akumulator independen di meter, menjumlahkannya jadi
                                 satu "stand total" tidak berarti apa-apa secara fisik. --}}
                            <td class="num">
                                {{ $meter->latestReading ? kwh($meter->latestReading->stand_lwbp) : '—' }}
                            </td>
                            <td class="num">
                                {{ $meter->latestReading ? kwh($meter->latestReading->stand_wbp) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="table-empty">
                                {{ $connectionFilter ? 'Tidak ada perangkat dengan status tersebut.' : 'Belum ada power meter aktif.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
