<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="No invoice atau nama pelanggan…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="spacer"></div>
            @can('payment.bulk')
                <button type="button" class="btn btn-outline" wire:click="openImport">
                    <i data-lucide="upload" style="width:15px;height:15px"></i>
                    Impor Berkas
                </button>
            @endcan
            @can('payment.create')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="wallet" style="width:15px;height:15px"></i>
                    Catat Pembayaran
                </button>
            @endcan
        </div>
    </div>

    {{-- ── Entri cepat ─────────────────────────────────────────────────── --}}
    @can('payment.create')
        <div class="card mb-18">
            <div class="card-head" style="margin-bottom:12px">
                <div>
                    <div class="card-title">Entri Cepat</div>
                    <div class="card-sub">
                        Ketik atau pindai nomor invoice, tekan Tab — nominalnya terisi sisa tagihan.
                        Enter untuk menyimpan, lalu langsung lanjut ke invoice berikutnya.
                    </div>
                </div>
            </div>

            <form wire:submit="quickSave"
                  x-data
                  x-on:focus-quick-entry.window="$nextTick(() => $refs.invoiceNo.focus())">
                <div class="filter-bar" style="align-items:flex-end">
                    <div class="field" style="min-width:220px">
                        <label class="field-label">No Invoice</label>
                        <input type="text" class="input mono" x-ref="invoiceNo" autocomplete="off"
                               placeholder="INV/2026/07/001"
                               wire:model.blur="quickInvoiceNo">
                    </div>
                    <div class="field" style="min-width:150px">
                        <label class="field-label">Jumlah (Rp)</label>
                        <input type="number" step="0.01" min="0.01"
                               class="input mono @error('quickAmount') is-invalid @enderror"
                               wire:model.live.debounce.400ms="quickAmount">
                    </div>
                    <div class="field" style="min-width:140px">
                        <label class="field-label">Tanggal</label>
                        <input type="date" class="input mono @error('quickDate') is-invalid @enderror"
                               wire:model="quickDate">
                    </div>
                    <div class="field" style="min-width:130px">
                        <label class="field-label">Metode</label>
                        <x-select-search wire:model="quickMethod"
                            :options="[
                                ['value' => 'transfer', 'label' => 'Transfer'],
                                ['value' => 'cash', 'label' => 'Tunai'],
                                ['value' => 'other', 'label' => 'Lainnya'],
                            ]" />
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <i data-lucide="corner-down-left" style="width:15px;height:15px"></i>
                        Simpan
                    </button>
                </div>
            </form>

            {{-- Umpan balik nomor invoice: apa yang diketik memang invoice yang dimaksud. --}}
            @if ($quickInvoiceNo !== '')
                <div class="card-sub" style="margin-top:4px">
                    @if ($quickInvoice)
                        <strong>{{ $quickInvoice->customer_name }}</strong> ·
                        tagihan {{ rupiah($quickInvoice->total_amount) }} ·
                        sisa <strong>{{ rupiah($quickInvoice->outstanding) }}</strong>
                        · <x-invoice-status :status="$quickInvoice->status" />
                    @else
                        <span style="color:var(--danger)">Nomor invoice tidak ditemukan.</span>
                    @endif
                </div>

                <x-payment-preview :preview="$quickPreview" style="margin-top:10px" />
            @endif
        </div>
    @endcan

    {{-- ── Hasil impor ─────────────────────────────────────────────────── --}}
    @if ($importResult)
        <div class="alert {{ $importResult['failed'] ? 'alert-warning' : 'alert-success' }} mb-18">
            <div class="row" style="justify-content:space-between;align-items:flex-start;gap:16px">
                <div>
                    <strong>{{ $importResult['created'] }} pembayaran diimpor</strong>
                    — total {{ rupiah($importResult['total']) }}.

                    @if ($importResult['failed'])
                        <ul style="margin:8px 0 0;padding-left:18px">
                            @foreach ($importResult['failed'] as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="row nowrap" style="gap:8px">
                    @if ($importResult['batch_id'])
                        @can('payment.bulk')
                            <button type="button" class="btn btn-outline btn-sm"
                                    x-on:click="ConfirmDialog.show({
                                            title: 'Batalkan impor barusan?',
                                            text: 'Seluruh pembayaran dari berkas ini ditarik kembali dan status invoicenya dikembalikan.',
                                            danger: true,
                                            confirmText: 'Ya, Batalkan',
                                            onConfirm: () => $wire.revertBatch({{ $importResult['batch_id'] }}),
                                        })">
                                <i data-lucide="undo-2" style="width:14px;height:14px"></i>
                                Batalkan
                            </button>
                        @endcan
                    @endif
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="$set('importResult', null)">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Metode</th>
                        <th>Referensi</th>
                        <th class="num">Jumlah</th>
                        <th>Bukti</th>
                        <th>Kuitansi</th>
                        <th>Dicatat Oleh</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="mono">{{ $payment->payment_date->translatedFormat('d M Y') }}</td>
                            <td class="num strong" style="text-align:left">{{ $payment->invoice?->invoice_no ?? '—' }}</td>
                            <td>{{ $payment->invoice?->customer_name ?? '—' }}</td>
                            <td class="text-muted">{{ ucfirst($payment->method) }}</td>
                            <td class="text-muted mono">{{ $payment->reference_no ?: '—' }}</td>
                            <td class="num strong">{{ rupiah($payment->amount) }}</td>
                            <td>
                                @if ($payment->proof_path)
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($payment->proof_path) }}"
                                       target="_blank" class="link-action">Lihat</a>
                                @else
                                    <span class="text-faint">—</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                @can('payment.receipt')
                                    <a href="{{ route('billing.payments.receipt.preview', $payment) }}"
                                       target="_blank" class="link-action">
                                        {{ $payment->hasReceipt() ? $payment->receipt_no : 'Terbitkan' }}
                                    </a>
                                    @if ($payment->receiptSent())
                                        <div class="sub" style="color:var(--success)">
                                            Terkirim {{ $payment->receipt_sent_at->translatedFormat('d M') }}
                                        </div>
                                    @else
                                        <div class="sub">Belum dikirim</div>
                                    @endif
                                @else
                                    <span class="text-faint">{{ $payment->receipt_no ?? '—' }}</span>
                                @endcan
                            </td>
                            <td class="text-muted">{{ $payment->recordedBy?->name ?? '—' }}</td>
                            <td class="text-right nowrap">
                                @can('payment.receipt')
                                    <span class="link-action" style="margin-right:12px"
                                          x-on:click="ConfirmDialog.show({
                                                  title: 'Kirim kuitansi ke pelanggan?',
                                                  text: @js(($payment->receiptSent() ? 'Kuitansi ini sudah pernah dikirim. ' : '').'Terkirim ke email pelanggan yang terdaftar beserta lampiran PDF.'),
                                                  confirmText: 'Ya, Kirim',
                                                  onConfirm: () => $wire.sendReceipt({{ $payment->id }}),
                                              })">
                                        {{ $payment->receiptSent() ? 'Kirim Ulang' : 'Kirim' }}
                                    </span>
                                @endcan
                                @can('payment.delete')
                                    <span class="link-action danger" x-on:click="ConfirmDialog.show({
                                            title: 'Hapus pembayaran ini?',
                                            text: 'Status invoice akan dihitung ulang.',
                                            danger: true,
                                            confirmText: 'Ya, Hapus',
                                            onConfirm: () => $wire.delete({{ $payment->id }}),
                                        })">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="table-empty">
                                {{ $search ? 'Tidak ada pembayaran yang cocok.' : 'Belum ada pembayaran tercatat.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div style="margin-top:16px">{{ $payments->links() }}</div>
        @endif
    </div>

    {{-- ── Riwayat operasi massal ──────────────────────────────────────── --}}
    @can('payment.bulk')
        @if ($batches->isNotEmpty())
            <div class="card" style="margin-top:18px">
                <div class="card-head">
                    <div>
                        <div class="card-title">Operasi Massal Terakhir</div>
                        <div class="card-sub">
                            Lima terakhir. Membatalkan satu batch menarik kembali seluruh pembayarannya
                            sekaligus dan mengembalikan status invoicenya.
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Jenis</th>
                                <th>Sumber</th>
                                <th class="num">Jumlah</th>
                                <th class="num">Total</th>
                                <th>Oleh</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($batches as $batch)
                                <tr @if ($batch->isReverted()) style="opacity:.55" @endif>
                                    <td class="mono text-muted">{{ $batch->created_at->translatedFormat('d M Y H:i') }}</td>
                                    <td>{{ $batch->typeLabel() }}</td>
                                    <td class="text-muted mono">{{ $batch->source ?? '—' }}</td>
                                    <td class="num">{{ $batch->payment_count }}</td>
                                    <td class="num strong">{{ rupiah($batch->total_amount) }}</td>
                                    <td class="text-muted">{{ $batch->createdBy?->name ?? '—' }}</td>
                                    <td class="text-right nowrap">
                                        @if ($batch->isReverted())
                                            <span class="badge badge-neutral">Dibatalkan</span>
                                        @elseif ($batch->sent_receipts_count > 0)
                                            {{-- Kuitansinya sudah dipegang pelanggan: pembatalan biasa
                                                 ditutup, hanya pemegang izin khusus yang bisa lanjut. --}}
                                            @can('payment.force_revert')
                                                <span class="link-action danger"
                                                      x-on:click="ConfirmDialog.show({
                                                              title: 'Batalkan paksa batch ini?',
                                                              text: '{{ $batch->sent_receipts_count }} kuitansi sudah dikirim ke pelanggan. Membatalkan berarti menarik dokumen yang sudah mereka pegang — mereka akan otomatis dikirimi pemberitahuan pembatalan.',
                                                              danger: true,
                                                              confirmText: 'Ya, Batalkan Paksa',
                                                              prompt: {
                                                                  label: 'Alasan pembatalan (ikut dikirim ke pelanggan)',
                                                                  placeholder: 'Mis. salah catat, pembayaran belum masuk rekening',
                                                              },
                                                              onConfirm: (reason) => $wire.forceRevertBatch({{ $batch->id }}, reason),
                                                          })">Batalkan Paksa</span>
                                            @else
                                                <span class="text-faint"
                                                      title="{{ $batch->sent_receipts_count }} kuitansi sudah dikirim ke pelanggan">
                                                    Terkunci
                                                </span>
                                            @endcan
                                        @else
                                            <span class="link-action danger"
                                                  x-on:click="ConfirmDialog.show({
                                                          title: 'Batalkan batch ini?',
                                                          text: '{{ $batch->payment_count }} pembayaran senilai {{ rupiah($batch->total_amount) }} akan ditarik kembali.',
                                                          danger: true,
                                                          confirmText: 'Ya, Batalkan',
                                                          onConfirm: () => $wire.revertBatch({{ $batch->id }}),
                                                      })">Batalkan</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endcan

    {{-- ── Form ────────────────────────────────────────────────────────── --}}
    @if ($showForm)
        <div class="modal-overlay" wire:click.self="$set('showForm', false)">
            <div class="modal modal-sm">
                <div class="card-title" style="margin-bottom:6px">Catat Pembayaran</div>
                <div class="card-sub" style="margin-bottom:20px">
                    Status invoice diperbarui otomatis: lunas bila jumlahnya menutup seluruh tagihan,
                    bayar sebagian bila belum.
                </div>

                <form wire:submit="save">
                    <div class="field">
                        <label class="field-label">Invoice <span style="color:var(--danger)">*</span></label>
                        <x-select-search
                            wire:model.live="form.invoice_id"
                            :invalid="$errors->has('form.invoice_id')"
                            placeholder="— pilih invoice —"
                            search-placeholder="Cari no invoice atau pelanggan…"
                            :options="$openInvoices->map(fn ($invoice) => [
                                'value' => $invoice->id,
                                'label' => $invoice->invoice_no.' — '.$invoice->customer_name,
                                'sub' => 'Sisa '.rupiah($invoice->total_amount - $invoice->paid_amount),
                            ])" />
                        @error('form.invoice_id') <div class="field-error">{{ $message }}</div> @enderror
                        @if ($openInvoices->isEmpty())
                            <div class="card-sub">Tidak ada invoice yang menunggu pembayaran.</div>
                        @endif
                    </div>

                    <div class="field">
                        <label class="field-label">Tanggal Bayar <span style="color:var(--danger)">*</span></label>
                        <input type="date" class="input @error('form.payment_date') is-invalid @enderror"
                               wire:model="form.payment_date">
                        @error('form.payment_date') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Jumlah (Rp) <span style="color:var(--danger)">*</span></label>
                        <input type="number" step="0.01" min="0.01"
                               class="input mono @error('form.amount') is-invalid @enderror"
                               wire:model.live.debounce.400ms="form.amount">
                        @error('form.amount') <div class="field-error">{{ $message }}</div> @enderror
                        <x-payment-preview :preview="$formPreview" style="margin-top:10px" />
                    </div>

                    <div class="field">
                        <label class="field-label">Metode</label>
                        <x-select-search wire:model="form.method"
                            :options="[
                                ['value' => 'transfer', 'label' => 'Transfer'],
                                ['value' => 'cash', 'label' => 'Tunai'],
                                ['value' => 'other', 'label' => 'Lainnya'],
                            ]" />
                    </div>

                    <div class="field">
                        <label class="field-label">Nomor Referensi</label>
                        <input type="text" class="input mono" wire:model="form.reference_no"
                               placeholder="Nomor transaksi bank">
                    </div>

                    <div class="field">
                        <label class="field-label">Bukti Transfer</label>
                        <input type="file" class="input @error('proof') is-invalid @enderror"
                               accept=".jpg,.jpeg,.png,.pdf" wire:model="proof">
                        @error('proof') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">JPG, PNG, atau PDF — maksimal 4 MB.</div>
                        <div wire:loading wire:target="proof" class="card-sub">Mengunggah…</div>
                    </div>

                    <div class="field">
                        <label class="field-label">Catatan</label>
                        <textarea class="textarea" wire:model="form.notes"></textarea>
                    </div>

                    <div class="row" style="margin-top:24px">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <i data-lucide="check" style="width:15px;height:15px"></i>
                            Simpan
                        </button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showForm', false)">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ── Impor berkas ────────────────────────────────────────────────── --}}
    @if ($showImport)
        <div class="modal-overlay" wire:click.self="$set('showImport', false)">
            <div class="modal">
                <div class="card-head" style="margin-bottom:18px">
                    <div>
                        <div class="card-title">Impor Pembayaran</div>
                        <div class="card-sub">
                            Unduh templatnya, tempelkan hasil pivot dari mutasi bank, lalu unggah kembali.
                            Tidak ada yang tersimpan sebelum Anda menekan Simpan.
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost" wire:click="$set('showImport', false)">Tutup</button>
                </div>

                <div class="row" style="gap:10px;margin-bottom:18px;flex-wrap:wrap">
                    <a href="{{ route('billing.payments.template') }}" class="btn btn-outline btn-sm">
                        <i data-lucide="download" style="width:14px;height:14px"></i>
                        Unduh Template
                    </a>
                    <span class="card-sub" style="align-self:center">
                        Kolom: {{ implode(', ', \App\Services\Billing\PaymentImportService::COLUMNS) }} ·
                        maksimal {{ \App\Services\Billing\PaymentImportService::MAX_ROWS }} baris
                    </span>
                </div>

                <div class="field">
                    <label class="field-label">Berkas <span style="color:var(--danger)">*</span></label>
                    <input type="file" class="input @error('importFile') is-invalid @enderror"
                           accept=".xlsx,.xls,.csv" wire:model="importFile">
                    @error('importFile') <div class="field-error">{{ $message }}</div> @enderror
                    <div class="card-sub">XLSX, XLS, atau CSV — maksimal 4 MB.</div>
                    <div wire:loading wire:target="importFile" class="card-sub">Mengunggah…</div>
                </div>

                <div class="row" style="margin-top:14px">
                    <button type="button" class="btn btn-outline" wire:click="previewImport"
                            wire:loading.attr="disabled" wire:target="previewImport,importFile">
                        <i data-lucide="search-check" style="width:15px;height:15px"></i>
                        <span wire:loading.remove wire:target="previewImport">Periksa Berkas</span>
                        <span wire:loading wire:target="previewImport">Memeriksa…</span>
                    </button>
                </div>

                @if ($importSummary)
                    <div class="alert {{ $importSummary['invalid'] ? 'alert-warning' : 'alert-success' }}"
                         style="margin-top:18px">
                        <strong>{{ $importSummary['valid'] }} baris siap disimpan</strong>
                        (total {{ rupiah($importSummary['total']) }}){{ $importSummary['invalid'] ? ',' : '.' }}
                        @if ($importSummary['invalid'])
                            {{ $importSummary['invalid'] }} baris bermasalah dan akan dilewati.
                        @endif
                    </div>

                    <div class="table-wrap" style="margin-top:14px;max-height:340px;overflow-y:auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:48px">Baris</th>
                                    <th>No Invoice</th>
                                    <th>Pelanggan</th>
                                    <th>Tanggal</th>
                                    <th class="num">Jumlah</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importRows as $row)
                                    <tr @if (! $row['ok']) style="background:var(--danger-bg)" @endif>
                                        <td class="text-faint mono">{{ $row['line'] }}</td>
                                        <td class="mono">{{ $row['invoice_no'] ?: '—' }}</td>
                                        <td class="text-muted">{{ $row['customer_name'] ?: '—' }}</td>
                                        <td class="mono text-muted">{{ $row['payment_date'] ?? '—' }}</td>
                                        <td class="num">{{ $row['amount'] ? rupiah($row['amount']) : '—' }}</td>
                                        <td>
                                            @if ($row['ok'])
                                                <span class="badge badge-success">Siap</span>
                                            @else
                                                <span style="color:var(--danger-fg)">{{ $row['error'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row" style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-soft)">
                        <button type="button" class="btn btn-primary" wire:click="commitImport"
                                wire:loading.attr="disabled" wire:target="commitImport"
                                @disabled(! $importSummary['valid'])>
                            <i data-lucide="check" style="width:15px;height:15px"></i>
                            <span wire:loading.remove wire:target="commitImport">
                                Simpan {{ $importSummary['valid'] }} Pembayaran
                            </span>
                            <span wire:loading wire:target="commitImport">Menyimpan…</span>
                        </button>
                        <button type="button" class="btn btn-outline" wire:click="$set('showImport', false)">Batal</button>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>
