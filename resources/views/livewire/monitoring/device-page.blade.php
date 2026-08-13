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
            <div class="chip">
                <i data-lucide="info" style="width:14px;height:14px;color:var(--text-faint)"></i>
                Target hari ini ± {{ number_format($expected, 0, ',', '.') }} pembacaan per meter
            </div>
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
                        <th>Terakhir Kirim</th>
                        <th class="num">Data Hari Ini</th>
                        <th class="num">Stand Akhir</th>
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
                            $count = $todayCounts[$meter->id] ?? 0;
                            $completeness = $expected > 0 ? min(100, round($count / $expected * 100)) : 0;
                        @endphp
                        <tr>
                            <td class="strong">
                                {{ $meter->name }}
                                <div class="sub mono">{{ $meter->code }}</div>
                            </td>
                            <td>{{ $meter->customer?->name ?? '— belum terhubung' }}</td>
                            <td class="text-muted">{{ $meter->location ?: '—' }}</td>
                            <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td class="text-muted">
                                @if ($meter->last_seen_at)
                                    {{ $meter->last_seen_at->diffForHumans() }}
                                    <div class="sub mono">{{ $meter->last_seen_at->translatedFormat('d M Y H:i') }}</div>
                                @else
                                    Belum pernah
                                @endif
                            </td>
                            <td class="num">
                                {{ number_format($count, 0, ',', '.') }}
                                <div class="sub {{ $completeness < 90 ? '' : '' }}"
                                     style="color:{{ $completeness < 90 ? 'var(--danger)' : 'var(--success)' }}">
                                    {{ $completeness }}% lengkap
                                </div>
                            </td>
                            <td class="num">
                                {{ $meter->latestReading ? kwh($meter->latestReading->total_stand) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="table-empty">
                                {{ $connectionFilter ? 'Tidak ada perangkat dengan status tersebut.' : 'Belum ada power meter aktif.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
