<div>

    <div class="card mb-18">
        <div class="filter-bar">
            <div class="field">
                <label class="field-label">Cari</label>
                <input type="text" class="input" placeholder="No invoice atau nama pelanggan…"
                       wire:model.live.debounce.400ms="search">
            </div>
            <div class="spacer"></div>
            @can('payment.create')
                <button type="button" class="btn btn-primary" wire:click="create">
                    <i data-lucide="wallet" style="width:15px;height:15px"></i>
                    Catat Pembayaran
                </button>
            @endcan
        </div>
    </div>

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
                            <td class="text-muted">{{ $payment->recordedBy?->name ?? '—' }}</td>
                            <td class="text-right">
                                @can('payment.delete')
                                    <span class="link-action danger"
                                          wire:click="delete({{ $payment->id }})"
                                          wire:confirm="Hapus pembayaran ini? Status invoice akan dihitung ulang.">Hapus</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="table-empty">
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
                               class="input mono @error('form.amount') is-invalid @enderror" wire:model="form.amount">
                        @error('form.amount') <div class="field-error">{{ $message }}</div> @enderror
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

</div>
