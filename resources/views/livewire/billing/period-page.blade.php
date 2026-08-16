<div>

    {{-- ── Generate ────────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="card-head">
            <div>
                <div class="card-title">Generate Invoice</div>
                <div class="card-sub">
                    Pemakaian dihitung dari selisih stand meter awal dan akhir periode.
                    Otomatis dijalankan tiap tanggal {{ $defaultBillingDay }} pukul
                    {{ setting('billing_generate_time', '00:15') }} —
                    pelanggan dengan tanggal tagih sendiri mengikuti tanggalnya masing-masing.
                </div>
            </div>
        </div>

        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Periode yang Ditagih</label>
                <input type="month" class="input mono" wire:model="month">
            </div>
            <div class="field">
                <label class="field-label">Pelanggan Siap Ditagih</label>
                <div class="input mono" style="background:var(--bg-subtle)">{{ $billableCount }}</div>
            </div>
            <div class="spacer"></div>
            <label class="checkbox-row" style="margin:0 12px 8px 0">
                <input type="checkbox" wire:model="regenerate">
                <span>Buat ulang invoice draft</span>
            </label>
            @can('invoice.generate')
                <button type="button" class="btn btn-primary" wire:click="generate" wire:loading.attr="disabled">
                    <i data-lucide="file-plus-2" style="width:15px;height:15px"></i>
                    <span wire:loading.remove wire:target="generate">Generate</span>
                    <span wire:loading wire:target="generate">Memproses…</span>
                </button>
            @endcan
        </div>

        @if ($incompleteCount > 0)
            <div class="alert alert-warning" style="margin-top:16px">
                {{ $incompleteCount }} pelanggan aktif belum punya power meter atau golongan tarif,
                sehingga tidak ikut ditagih. Lengkapi datanya di menu Data Pelanggan.
            </div>
        @endif

        @if ($result)
            <div class="alert {{ $result['failed'] ? 'alert-warning' : 'alert-success' }}" style="margin-top:16px">
                <strong>{{ $result['created'] }} invoice dibuat</strong>, {{ $result['skipped'] }} dilewati
                (sudah punya invoice di periode ini).
                @if ($result['failed'])
                    <ul style="margin:8px 0 0;padding-left:18px">
                        @foreach ($result['failed'] as $failure)
                            <li>{{ $failure }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    {{-- ── Daftar periode ──────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-head">
            <div class="card-title">Riwayat Periode</div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Rentang</th>
                        <th>Cut-off</th>
                        <th class="num">Invoice</th>
                        <th class="num">Nilai Tagihan</th>
                        <th>Status</th>
                        <th>Digenerate</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        @php
                            $badge = match ($period->status) {
                                'closed' => ['Ditutup', 'badge-neutral'],
                                'generated' => ['Sudah Digenerate', 'badge-success'],
                                default => ['Terbuka', 'badge-warning'],
                            };
                        @endphp
                        <tr>
                            <td class="strong mono">{{ $period->code }}</td>
                            <td class="text-muted">
                                {{ $period->period_start->translatedFormat('d M') }} –
                                {{ $period->period_end->translatedFormat('d M Y') }}
                            </td>
                            <td class="text-muted">{{ $period->cut_off_date->translatedFormat('d M Y') }}</td>
                            <td class="num">{{ $period->invoices_count }}</td>
                            <td class="num strong">{{ rupiah($period->total_amount_sum ?? 0) }}</td>
                            <td><span class="badge {{ $badge[1] }}">{{ $badge[0] }}</span></td>
                            <td class="text-muted">
                                {{ $period->generated_at?->translatedFormat('d M Y H:i') ?? '—' }}
                            </td>
                            <td class="text-right nowrap">
                                @can('invoice.generate')
                                    @if ($period->status === 'closed')
                                        <span class="link-action muted" x-on:click="ConfirmDialog.show({
                                                title: 'Buka kembali periode ' + @js($period->code) + '?',
                                                confirmText: 'Ya, Buka Kembali',
                                                onConfirm: () => $wire.reopen({{ $period->id }}),
                                            })">Buka Kembali</span>
                                    @elseif ($period->invoices_count > 0)
                                        <span class="link-action" x-on:click="ConfirmDialog.show({
                                                title: 'Tutup periode ' + @js($period->code) + '?',
                                                text: 'Invoice di dalamnya tidak bisa digenerate ulang.',
                                                confirmText: 'Ya, Tutup',
                                                onConfirm: () => $wire.close({{ $period->id }}),
                                            })">Tutup Periode</span>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="table-empty">Belum ada periode billing. Generate periode pertama di atas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
