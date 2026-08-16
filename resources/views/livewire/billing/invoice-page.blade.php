<div>

    {{-- ── Ringkasan ───────────────────────────────────────────────────── --}}
    <div class="stat-grid grid-1-1-1">
        <div class="card">
            <div class="stat-label">Belum Dibayar</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($summary['outstanding']) }}</div>
            <div class="stat-foot {{ $summary['overdue_count'] > 0 ? 'down' : '' }}">
                {{ $summary['overdue_count'] }} invoice jatuh tempo
            </div>
        </div>
        <div class="card">
            <div class="stat-label">Terbayar {{ $summary['paid_last_month_label'] }}</div>
            <div class="stat-value sm" style="margin-top:8px">{{ rupiah($summary['paid_last_month']) }}</div>
            <div class="stat-foot up">Invoice berstatus lunas</div>
        </div>
        <div class="card">
            <div class="stat-label">Draft Menunggu Terbit</div>
            <div class="stat-value sm" style="margin-top:8px">{{ $summary['draft_count'] }}</div>
            <div class="stat-foot">Belum ditagihkan ke pelanggan</div>
        </div>
    </div>

    {{-- ── Filter ──────────────────────────────────────────────────────── --}}
    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="No invoice, pelanggan, atau meter…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="field">
                <label class="field-label">Periode</label>
                <x-select-search
                    wire:model.live="periodFilter"
                    placeholder="Semua periode"
                    search-placeholder="Cari periode…"
                    :options="$periods
                        ->map(fn ($period) => ['value' => $period->id, 'label' => $period->code])
                        ->prepend(['value' => '', 'label' => 'Semua periode'])" />
            </div>
            <div class="field">
                <label class="field-label">Status</label>
                <x-select-search wire:model.live="statusFilter" placeholder="Semua status"
                    :options="[
                        ['value' => '', 'label' => 'Semua status'],
                        ['value' => 'draft', 'label' => 'Draft'],
                        ['value' => 'issued', 'label' => 'Belum Bayar'],
                        ['value' => 'partial', 'label' => 'Bayar Sebagian'],
                        ['value' => 'paid', 'label' => 'Lunas'],
                        ['value' => 'overdue', 'label' => 'Jatuh Tempo'],
                        ['value' => 'cancelled', 'label' => 'Dibatalkan'],
                    ]" />
            </div>
        </div>
    </div>

    {{-- ── Hasil operasi massal ────────────────────────────────────────── --}}
    @if ($bulkResult)
        <div class="alert {{ $bulkResult['skipped'] ? 'alert-warning' : 'alert-success' }} mb-18">
            <div class="row" style="justify-content:space-between;align-items:flex-start;gap:16px">
                <div>
                    <strong>{{ $bulkResult['created'] }} invoice ditandai lunas</strong>
                    — total {{ rupiah($bulkResult['total']) }}.

                    @if ($bulkResult['skipped'])
                        <div style="margin-top:8px">{{ count($bulkResult['skipped']) }} invoice dilewati:</div>
                        <ul style="margin:4px 0 0;padding-left:18px">
                            @foreach ($bulkResult['skipped'] as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="row nowrap" style="gap:8px">
                    @if ($bulkResult['batch_id'])
                        @can('payment.bulk')
                            <button type="button" class="btn btn-outline btn-sm"
                                    x-on:click="ConfirmDialog.show({
                                            title: 'Batalkan pelunasan barusan?',
                                            text: 'Seluruh {{ $bulkResult['created'] }} pembayaran dari operasi ini ditarik kembali dan status invoicenya dikembalikan.',
                                            danger: true,
                                            confirmText: 'Ya, Batalkan',
                                            onConfirm: () => $wire.revertBulk({{ $bulkResult['batch_id'] }}),
                                        })">
                                <i data-lucide="undo-2" style="width:14px;height:14px"></i>
                                Batalkan
                            </button>
                        @endcan
                    @endif
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('bulkResult', null)">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Tabel ───────────────────────────────────────────────────────── --}}
    <div class="card">
        @can('payment.bulk')
            @if ($selected)
                <div class="bulk-bar">
                    <span><strong>{{ count($selected) }}</strong> invoice dipilih</span>
                    <div class="spacer"></div>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('selected', [])">
                        Bersihkan
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="openBulk">
                        <i data-lucide="wallet" style="width:14px;height:14px"></i>
                        Tandai Lunas
                    </button>
                </div>
            @endif
        @endcan

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        @can('payment.bulk')
                            <th style="width:34px">
                                <input type="checkbox" title="Pilih semua yang bisa dibayar di halaman ini"
                                       wire:click="toggleSelectAll" @checked($selected)>
                            </th>
                        @endcan
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Meter</th>
                        <th>Periode</th>
                        <th class="num">LWBP</th>
                        <th class="num">WBP</th>
                        <th class="num">Tagihan</th>
                        <th class="num">Sisa</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php $blocked = $bulkService->rejectionReason($invoice); @endphp
                        <tr class="clickable" wire:click="show({{ $invoice->id }})">
                            @can('payment.bulk')
                                <td wire:click.stop>
                                    {{-- Invoice draft, batal, atau sudah lunas tidak bisa dicentang —
                                         lebih jelas begini daripada dicentang lalu ditolak diam-diam. --}}
                                    <input type="checkbox" value="{{ $invoice->id }}" wire:model.live="selected"
                                           @disabled($blocked) title="{{ $blocked ? 'Tidak bisa dibayar — '.$blocked : '' }}">
                                </td>
                            @endcan
                            <td class="num strong" style="text-align:left;color:var(--primary)">{{ $invoice->invoice_no }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td class="num text-muted" style="text-align:left">{{ $invoice->meter_code ?? '—' }}</td>
                            <td class="text-muted">
                                {{ $invoice->period_start->translatedFormat('d M') }} –
                                {{ $invoice->period_end->translatedFormat('d M Y') }}
                            </td>
                            <td class="num">{{ kwh($invoice->kwh_lwbp) }}</td>
                            <td class="num">{{ kwh($invoice->kwh_wbp) }}</td>
                            <td class="num strong">{{ rupiah($invoice->total_amount) }}</td>
                            <td class="num" style="color:{{ $invoice->outstanding > 0 ? 'var(--danger)' : 'var(--success)' }}">
                                {{ rupiah($invoice->outstanding) }}
                            </td>
                            <td><x-invoice-status :status="$invoice->status" /></td>
                            <td class="text-right nowrap" wire:click.stop>
                                <a href="{{ route('billing.invoices.preview', $invoice) }}" target="_blank"
                                   class="link-action muted" style="margin-right:12px">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->hasPermission('payment.bulk') ? 11 : 10 }}" class="table-empty">
                                {{ $search || $statusFilter || $periodFilter
                                    ? 'Tidak ada invoice yang cocok dengan filter.'
                                    : 'Belum ada invoice. Generate lewat menu Periode & Generate.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($invoices->hasPages())
            <div style="margin-top:16px">{{ $invoices->links() }}</div>
        @endif
    </div>

    {{-- ── Detail invoice ──────────────────────────────────────────────── --}}
    @if ($detail)
        <div class="modal-overlay" wire:click.self="$set('detailId', null)">
            <div class="modal">

                <div class="invoice-head">
                    <div>
                        <div class="invoice-kicker">INVOICE PEMAKAIAN LISTRIK</div>
                        <div class="invoice-no">{{ $detail->invoice_no }}</div>
                        <div style="margin-top:10px"><x-invoice-status :status="$detail->status" /></div>
                    </div>
                    <div class="invoice-issuer">
                        <strong>{{ setting('company_name') }}</strong>
                        {{ setting('company_address') }}<br>
                        {{ setting('company_domain') }}
                    </div>
                </div>

                @if ($detail->isCancelled())
                    <div class="alert alert-danger" style="margin-top:18px">
                        <strong>Invoice ini dibatalkan.</strong>
                        Dokumennya tidak berlaku sebagai tagihan dan tidak bisa dikirim ke pelanggan.
                        @if ($detail->cancelled_at)
                            <div style="margin-top:6px;font-size:12px">
                                Dibatalkan {{ $detail->cancelled_at->translatedFormat('d M Y, H:i') }}
                                @if ($detail->cancelledBy) oleh {{ $detail->cancelledBy->name }} @endif
                            </div>
                        @endif
                        @if ($detail->cancel_reason)
                            <div style="margin-top:4px;font-size:12px">Alasan: {{ $detail->cancel_reason }}</div>
                        @endif
                    </div>
                @endif

                <div class="invoice-meta">
                    <div>
                        <div class="field-label">Ditagihkan Kepada</div>
                        <div style="font-size:15px;font-weight:700">{{ $detail->customer_name }}</div>
                        <div class="text-muted" style="font-size:13px;margin-top:4px;line-height:1.6">
                            {{ $detail->customer_address }}
                        </div>
                    </div>
                    <div class="invoice-meta-grid">
                        <div>
                            <div class="field-label">Periode</div>
                            <div style="font-size:13px;font-weight:600">
                                {{ $detail->period_start->translatedFormat('d M') }} –
                                {{ $detail->period_end->translatedFormat('d M Y') }}
                            </div>
                        </div>
                        <div>
                            <div class="field-label">Jatuh Tempo</div>
                            <div style="font-size:13px;font-weight:600">
                                {{ $detail->due_date?->translatedFormat('d M Y') ?? '—' }}
                            </div>
                        </div>
                        <div>
                            <div class="field-label">Power Meter</div>
                            <div class="mono" style="font-size:13px;font-weight:600">{{ $detail->meter_code ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="field-label">Golongan</div>
                            <div style="font-size:13px;font-weight:600">{{ $detail->tariff_group_code ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table invoice-table">
                        <thead>
                            <tr>
                                <th>Uraian</th>
                                <th class="num">Stand Awal</th>
                                <th class="num">Stand Akhir</th>
                                <th class="num">kWh</th>
                                <th class="num">Tarif</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailLines as $line)
                                <tr>
                                    <td class="strong">{{ $line['label'] }}</td>
                                    <td class="num text-muted">{{ $line['start'] }}</td>
                                    <td class="num text-muted">{{ $line['end'] }}</td>
                                    <td class="num">{{ $line['kwh'] }}</td>
                                    <td class="num">{{ $line['rate'] }}</td>
                                    <td class="num strong">{{ rupiah($line['amount'], false) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="invoice-totals" style="margin-top:18px">
                    <div class="invoice-totals-inner">
                        @foreach ($detailTotals as $row)
                            <div class="invoice-total-row">
                                <span>{{ $row['label'] }}</span>
                                <span class="value">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                        <div class="invoice-grand">
                            <span class="label">TOTAL TAGIHAN</span>
                            <span class="value">{{ rupiah($detail->total_amount) }}</span>
                        </div>
                        @if ($detail->paid_amount > 0)
                            <div class="invoice-total-row" style="margin-top:10px">
                                <span>Sudah dibayar</span>
                                <span class="value" style="color:var(--success)">{{ rupiah($detail->paid_amount) }}</span>
                            </div>
                            <div class="invoice-total-row">
                                <span>Sisa tagihan</span>
                                <span class="value" style="color:var(--danger)">{{ rupiah($detail->outstanding) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($detail->notes)
                    <div class="alert alert-warning" style="margin-top:20px">{{ $detail->notes }}</div>
                @endif

                @if ($detail->payments->isNotEmpty())
                    <div style="margin-top:24px">
                        <div class="card-title" style="margin-bottom:10px">Riwayat Pembayaran</div>
                        @foreach ($detail->payments as $payment)
                            <div class="kv-row">
                                <span class="kv-label">
                                    {{ $payment->payment_date->translatedFormat('d M Y') }} ·
                                    {{ ucfirst($payment->method) }}
                                    {{ $payment->reference_no ? ' · '.$payment->reference_no : '' }}
                                </span>
                                <span class="kv-value">{{ rupiah($payment->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="row" style="margin-top:26px;padding-top:20px;border-top:1px solid var(--border-soft);flex-wrap:wrap">
                    @if ($detail->status === 'draft')
                        @can('invoice.update')
                            <button type="button" class="btn btn-primary" wire:click="issue({{ $detail->id }})">
                                <i data-lucide="check" style="width:15px;height:15px"></i>
                                Terbitkan
                            </button>
                        @endcan
                    @endif

                    {{-- Invoice batal tidak boleh dikirim: dokumennya sudah tidak berlaku. --}}
                    @if (! $detail->isCancelled())
                        @can('invoice.send')
                            <button type="button" class="btn btn-outline"
                                    wire:loading.attr="disabled"
                                    x-on:click="ConfirmDialog.show({
                                            title: 'Kirim invoice ' + @js($detail->invoice_no) + '?',
                                            text: 'Terkirim ke email pelanggan yang terdaftar.',
                                            confirmText: 'Ya, Kirim',
                                            onConfirm: () => $wire.sendEmail({{ $detail->id }}),
                                        })">
                                <i data-lucide="mail" style="width:15px;height:15px"></i>
                                <span wire:loading.remove wire:target="sendEmail">Kirim ke Pelanggan</span>
                                <span wire:loading wire:target="sendEmail">Mengirim…</span>
                            </button>
                        @endcan
                    @endif

                    <a href="{{ route('billing.invoices.download', $detail) }}" class="btn btn-outline">
                        <i data-lucide="download" style="width:15px;height:15px"></i>
                        Unduh PDF
                    </a>

                    @if ($detail->isCancellable())
                        @can('invoice.delete')
                            <button type="button" class="btn btn-ghost" style="color:var(--danger)"
                                    x-on:click="ConfirmDialog.show({
                                            title: 'Batalkan invoice ' + @js($detail->invoice_no) + '?',
                                            text: @js($detail->status === 'draft'
                                                ? 'Invoice tetap tersimpan dengan nomor yang sama, tapi tidak lagi dihitung sebagai tagihan.'
                                                : 'Invoice ini sudah terbit. Nomornya tetap dipakai, dan PDF-nya akan bertanda DIBATALKAN.'),
                                            danger: true,
                                            confirmText: 'Ya, Batalkan',
                                            prompt: {
                                                label: 'Alasan pembatalan (opsional)',
                                                placeholder: 'Mis. salah stand meter, pelanggan pindah golongan…',
                                            },
                                            onConfirm: (reason) => $wire.cancel({{ $detail->id }}, reason),
                                        })">
                                Batalkan
                            </button>
                        @endcan
                    @endif

                    @if ($detail->isCancelled())
                        @can('invoice.reopen')
                            <button type="button" class="btn btn-ghost" style="color:var(--primary)"
                                    x-on:click="ConfirmDialog.show({
                                            title: 'Buka kembali invoice ' + @js($detail->invoice_no) + '?',
                                            text: 'Invoice kembali menjadi draft dan harus diterbitkan ulang. Keterangan pembatalan pada dokumen akan dihapus.',
                                            icon: 'rotate-ccw',
                                            confirmText: 'Ya, Buka Kembali',
                                            onConfirm: () => $wire.reopen({{ $detail->id }}),
                                        })">
                                <i data-lucide="rotate-ccw" style="width:15px;height:15px"></i>
                                Buka Kembali
                            </button>
                        @endcan
                    @endif

                    <button type="button" class="btn btn-ghost" style="margin-left:auto"
                            wire:click="$set('detailId', null)">Tutup</button>
                </div>

            </div>
        </div>
    @endif

    {{-- ── Pelunasan massal ────────────────────────────────────────────── --}}
    @if ($showBulk)
        <div class="modal-overlay" wire:click.self="$set('showBulk', false)">
            <div class="modal modal-sm">
                <div class="card-title" style="margin-bottom:6px">Tandai Lunas</div>
                <div class="card-sub" style="margin-bottom:20px">
                    {{ count($selected) }} invoice akan dicatat lunas sebesar <strong>sisa tagihannya
                    masing-masing</strong>. Untuk pembayaran sebagian, catat satu per satu lewat menu Pembayaran.
                </div>

                <form wire:submit="bulkMarkPaid">
                    <div class="field">
                        <label class="field-label">Tanggal Bayar <span style="color:var(--danger)">*</span></label>
                        <input type="date" class="input @error('bulkForm.payment_date') is-invalid @enderror"
                               wire:model="bulkForm.payment_date">
                        @error('bulkForm.payment_date') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Metode</label>
                        <x-select-search wire:model="bulkForm.method"
                            :options="[
                                ['value' => 'transfer', 'label' => 'Transfer'],
                                ['value' => 'cash', 'label' => 'Tunai'],
                                ['value' => 'other', 'label' => 'Lainnya'],
                            ]" />
                    </div>

                    <div class="field">
                        <label class="field-label">Catatan</label>
                        <textarea class="textarea" wire:model="bulkForm.notes"
                                  placeholder="Mis. rekonsiliasi mutasi BCA 15 Agustus"></textarea>
                        <div class="card-sub">Dicatat pada seluruh pembayaran dalam operasi ini.</div>
                    </div>

                    <div class="row" style="margin-top:24px">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <i data-lucide="check" style="width:15px;height:15px"></i>
                            <span wire:loading.remove wire:target="bulkMarkPaid">Tandai Lunas</span>
                            <span wire:loading wire:target="bulkMarkPaid">Memproses…</span>
                        </button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showBulk', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
