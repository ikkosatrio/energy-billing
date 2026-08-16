<div>

    @if ($meters->isEmpty())
        <div class="card">
            <div class="table-empty">
                Belum ada power meter. Tambahkan perangkat terlebih dahulu di menu Power Meter Device.
            </div>
        </div>
    @else

        <div class="card mb-18">
            <div class="filter-bar">
                <div class="field">
                    <label class="field-label">Power Meter</label>
                    <x-select-search
                        wire:model.live="meterId"
                        search-placeholder="Cari kode atau nama meter…"
                        :options="$meters->map(fn ($option) => [
                            'value' => $option->id,
                            'label' => $option->code.' — '.$option->name,
                            'sub' => 'ID meter '.$option->id,
                        ])" />
                </div>
                @if ($meter)
                    <div class="field">
                        <label class="field-label">Pelanggan</label>
                        <div class="input" style="background:var(--bg-subtle)">
                            {{ $meter->customer?->name ?? '— belum terhubung —' }}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Lokasi</label>
                        <div class="input" style="background:var(--bg-subtle)">{{ $meter->location ?: '—' }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="alert alert-info mb-18">
            Meter mengirim register LWBP dan WBP secara terpisah, jadi jadwal ini
            <strong>tidak dipakai untuk membagi kWh</strong>. Fungsinya sebagai referensi konfigurasi
            di aplikasi — menentukan tarif aktif yang ditampilkan, mewarnai chart, dan menjadi acuan
            saat mencocokkan dengan setelan di perangkat.
        </div>

        <div class="grid grid-2">

            {{-- ── Daftar periode ──────────────────────────────────────── --}}
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">
                            Jadwal WBP / LWBP
                            <span class="text-faint" style="font-weight:500;font-size:13px">
                                (maksimal {{ \App\Models\MeterTariffSchedule::MAX_PERIODS }} periode)
                            </span>
                        </div>
                    </div>
                    <div class="row" style="gap:8px">
                        @can('tariff.update')
                            <button type="button" class="btn btn-outline btn-sm" wire:click="$set('showCopy', true)">
                                <i data-lucide="copy" style="width:14px;height:14px"></i>
                                Duplikat dari…
                            </button>
                        @endcan
                        <button type="button" class="btn btn-outline btn-sm" wire:click="addPeriod">
                            <i data-lucide="plus" style="width:14px;height:14px"></i>
                            Tambah Periode
                        </button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:48px">No</th>
                                <th>Mulai (WIB)</th>
                                <th>Tarif</th>
                                <th>Sampai</th>
                                <th>Durasi</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($periods as $index => $period)
                                @php $preview = $rows[$index] ?? null; @endphp
                                <tr>
                                    <td class="text-faint">{{ $index + 1 }}</td>
                                    <td>
                                        <input type="time" step="900" class="input mono" style="padding:7px 10px"
                                               wire:model.live="periods.{{ $index }}.start_time">
                                    </td>
                                    <td>
                                        <x-select-search wire:model.live="periods.{{ $index }}.tariff_type"
                                            :options="[
                                                ['value' => 'LWBP', 'label' => 'LWBP (Flat)'],
                                                ['value' => 'WBP', 'label' => 'WBP (Peak)'],
                                            ]" />
                                    </td>
                                    <td class="mono text-muted">{{ $preview['end_time'] ?? '—' }}</td>
                                    <td class="text-muted">
                                        @if ($preview)
                                            @php
                                                $mins = app(\App\Services\Tariff\ScheduleValidator::class);
                                                $s = $mins->minutesOf($preview['start_time']);
                                                $e = $mins->minutesOf($preview['end_time']);
                                                $dur = $e > $s ? $e - $s : (1440 - $s) + $e;
                                            @endphp
                                            {{ intdiv($dur, 60) }} jam{{ $dur % 60 ? ' '.($dur % 60).' mnt' : '' }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <span class="link-action danger" wire:click="removePeriod({{ $index }})">Hapus</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row" style="justify-content:space-between;padding:14px 12px 0;font-size:13px;font-weight:700">
                    <span style="color:var(--wbp-fg)">Total WBP: {{ intdiv($totals['WBP'], 60) }} jam</span>
                    <span style="color:var(--lwbp-fg)">Total LWBP: {{ intdiv($totals['LWBP'], 60) }} jam</span>
                </div>

                <div class="row" style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-soft)">
                    @can('tariff.update')
                        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <i data-lucide="save" style="width:15px;height:15px"></i>
                            Simpan Jadwal
                        </button>
                    @endcan
                    <button type="button" class="btn btn-outline" wire:click="loadSchedule">Batal</button>
                </div>
            </div>

            {{-- ── Pratinjau & validasi ────────────────────────────────── --}}
            <div class="stack">
                <div class="card">
                    <div class="card-title" style="margin-bottom:16px">Visualisasi 24 Jam</div>

                    @if ($rows)
                        @php $validator = app(\App\Services\Tariff\ScheduleValidator::class); @endphp
                        <div class="ribbon">
                            @foreach ($rows as $row)
                                @php
                                    $s = $validator->minutesOf($row['start_time']);
                                    $e = $validator->minutesOf($row['end_time']);
                                    $dur = $e > $s ? $e - $s : (1440 - $s) + $e;
                                @endphp
                                <div class="ribbon-seg {{ strtolower($row['tariff_type']) }}" style="flex:{{ $dur }}">
                                    {{ $dur >= 120 ? $row['tariff_type'] : '' }}
                                </div>
                            @endforeach
                        </div>
                        <div class="ribbon-scale">
                            <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>24:00</span>
                        </div>
                    @else
                        <div class="table-empty">Perbaiki kesalahan jadwal untuk melihat pratinjau.</div>
                    @endif
                </div>

                <div class="card">
                    <div class="card-title" style="margin-bottom:14px">Aturan & Validasi</div>

                    @if ($isValid)
                        <div class="alert alert-success">
                            <strong>Valid.</strong> Jadwal sudah memenuhi seluruh aturan dan siap disimpan.
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <strong>Belum valid:</strong>
                            <ul style="margin:8px 0 0;padding-left:18px">
                                @foreach (app(\App\Services\Tariff\ScheduleValidator::class)->validate($periods) as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div style="margin-top:14px">
                        <div class="kv-row">
                            <span class="kv-label">Jumlah periode</span>
                            <span class="kv-value">{{ count($periods) }} / {{ \App\Models\MeterTariffSchedule::MAX_PERIODS }}</span>
                        </div>
                        <div class="kv-row">
                            <span class="kv-label">Resolusi slot</span>
                            <span class="kv-value">{{ \App\Models\MeterTariffSchedule::SLOT_MINUTES }} menit</span>
                        </div>
                        <div class="kv-row">
                            <span class="kv-label">Total durasi</span>
                            <span class="kv-value">{{ intdiv($totals['LWBP'] + $totals['WBP'], 60) }} jam</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Duplikat jadwal dari meter lain ─────────────────────────── --}}
        @if ($showCopy)
            <div class="modal-overlay" wire:click.self="$set('showCopy', false)">
                <div class="modal">
                    <div class="card-head" style="margin-bottom:18px">
                        <div>
                            <div class="card-title">Duplikat Jadwal</div>
                            <div class="card-sub">
                                Pilih meter yang jadwalnya ingin disalin ke
                                <strong>{{ $meter?->code }}</strong>. Hasilnya masuk ke form dulu —
                                belum tersimpan sampai Anda klik Simpan Jadwal.
                            </div>
                        </div>
                        <button type="button" class="btn btn-ghost" wire:click="$set('showCopy', false)">Tutup</button>
                    </div>

                    @if ($copySources->isEmpty())
                        <div class="table-empty">
                            Belum ada meter lain yang punya jadwal tersimpan.
                        </div>
                    @else
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Meter</th>
                                        <th>Pelanggan</th>
                                        <th>Jadwal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($copySources as $source)
                                        <tr>
                                            <td class="strong">
                                                {{ $source['name'] }}
                                                <div class="sub mono">{{ $source['code'] }}</div>
                                            </td>
                                            <td class="text-muted">{{ $source['customer'] ?? '—' }}</td>
                                            <td>
                                                <div class="row" style="gap:6px;flex-wrap:wrap">
                                                    @foreach ($source['periods'] as $period)
                                                        <span class="badge badge-{{ strtolower($period['tariff_type']) }} badge-square mono">
                                                            {{ $period['start_time'] }} {{ $period['tariff_type'] }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="text-right nowrap">
                                                <span class="link-action" wire:click="copyFrom({{ $source['id'] }})">
                                                    Gunakan
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    @endif

</div>
