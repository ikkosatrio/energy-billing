<div>

    <form wire:submit="save">
        <div class="grid grid-2">

            {{-- ── Identitas ───────────────────────────────────────────── --}}
            <div class="card">
                <div class="card-title" style="margin-bottom:16px">Identitas Aplikasi</div>

                <div class="field">
                    <label class="field-label">Nama Aplikasi <span style="color:var(--danger)">*</span></label>
                    <input type="text" class="input @error('values.app_name') is-invalid @enderror"
                           wire:model="values.app_name">
                    @error('values.app_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label class="field-label">Nama Perusahaan <span style="color:var(--danger)">*</span></label>
                    <input type="text" class="input @error('values.company_name') is-invalid @enderror"
                           wire:model="values.company_name">
                    @error('values.company_name') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label class="field-label">Alamat</label>
                    <textarea class="textarea" wire:model="values.company_address"></textarea>
                </div>

                <div class="field">
                    <label class="field-label">Telepon</label>
                    <input type="text" class="input" wire:model="values.company_phone">
                </div>

                <div class="field">
                    <label class="field-label">Email</label>
                    <input type="email" class="input @error('values.company_email') is-invalid @enderror"
                           wire:model="values.company_email">
                    @error('values.company_email') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                <div class="field">
                    <label class="field-label">NPWP</label>
                    <input type="text" class="input mono" wire:model="values.company_npwp">
                </div>

                <div class="field">
                    <label class="field-label">Domain</label>
                    <input type="text" class="input" wire:model="values.company_domain"
                           placeholder="billing.perusahaan.co.id">
                </div>

                <div class="field">
                    <label class="field-label">Logo</label>
                    <input type="file" class="input @error('logo') is-invalid @enderror"
                           accept="image/*" wire:model="logo">
                    @error('logo') <div class="field-error">{{ $message }}</div> @enderror
                    <div class="card-sub">PNG atau SVG, maksimal 2 MB.</div>
                </div>
            </div>

            <div class="stack">
                {{-- ── Billing ─────────────────────────────────────────── --}}
                <div class="card">
                    <div class="card-title">Billing &amp; Invoice</div>
                    <div class="card-sub" style="margin-bottom:16px">
                        Persentase dan nominal di sini di-snapshot ke setiap invoice saat digenerate,
                        jadi mengubahnya tidak memengaruhi invoice yang sudah terbit.
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="field">
                            <label class="field-label">Tanggal Generate <span style="color:var(--danger)">*</span></label>
                            <input type="number" min="1" max="28"
                                   class="input mono @error('values.billing_cut_off_day') is-invalid @enderror"
                                   wire:model="values.billing_cut_off_day">
                            @error('values.billing_cut_off_day') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label class="field-label">Jam Generate <span style="color:var(--danger)">*</span></label>
                            <input type="time" class="input mono @error('values.billing_generate_time') is-invalid @enderror"
                                   wire:model="values.billing_generate_time">
                            @error('values.billing_generate_time') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label class="field-label">Jatuh Tempo (hari) <span style="color:var(--danger)">*</span></label>
                            <input type="number" min="0" class="input mono @error('values.invoice_due_days') is-invalid @enderror"
                                   wire:model="values.invoice_due_days">
                            @error('values.invoice_due_days') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label class="field-label">Digit Nomor Urut <span style="color:var(--danger)">*</span></label>
                            <input type="number" min="1" max="10"
                                   class="input mono @error('values.invoice_number_padding') is-invalid @enderror"
                                   wire:model="values.invoice_number_padding">
                            @error('values.invoice_number_padding') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label">Format Nomor Invoice <span style="color:var(--danger)">*</span></label>
                        <input type="text" class="input mono @error('values.invoice_number_format') is-invalid @enderror"
                               wire:model="values.invoice_number_format">
                        @error('values.invoice_number_format') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">
                            Placeholder: <span class="mono">{YYYY}</span> tahun,
                            <span class="mono">{YY}</span> tahun 2 digit,
                            <span class="mono">{MM}</span> bulan,
                            <span class="mono">{SEQ}</span> nomor urut.
                        </div>
                    </div>

                    <div class="form-grid form-grid-2">
                        <div class="field">
                            <label class="field-label">Biaya Admin (Rp) <span style="color:var(--danger)">*</span></label>
                            <input type="number" min="0" class="input mono @error('values.biaya_admin') is-invalid @enderror"
                                   wire:model="values.biaya_admin">
                            @error('values.biaya_admin') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label class="field-label">Pembulatan Total (Rp) <span style="color:var(--danger)">*</span></label>
                            <input type="number" min="0" class="input mono @error('values.invoice_rounding_to') is-invalid @enderror"
                                   wire:model="values.invoice_rounding_to">
                            @error('values.invoice_rounding_to') <div class="field-error">{{ $message }}</div> @enderror
                            <div class="card-sub">0 = tanpa pembulatan.</div>
                        </div>

                        <div class="field">
                            <label class="field-label">PPJ (%) <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="0.01" min="0" max="100"
                                   class="input mono @error('values.ppj_percent') is-invalid @enderror"
                                   wire:model="values.ppj_percent">
                            @error('values.ppj_percent') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label class="field-label">PPN (%) <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="0.01" min="0" max="100"
                                   class="input mono @error('values.ppn_percent') is-invalid @enderror"
                                   wire:model="values.ppn_percent">
                            @error('values.ppn_percent') <div class="field-error">{{ $message }}</div> @enderror
                            <div class="card-sub">Isi 0 bila tagihan tidak dikenakan PPN.</div>
                        </div>
                    </div>

                    {{-- ── Otomatisasi ─────────────────────────────────── --}}
                    <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-soft)">
                        <div class="field-label" style="margin-bottom:10px">Otomatisasi</div>

                        <label class="checkbox-row" style="margin:0">
                            <input type="checkbox" wire:model.live="values.invoice_auto_issue">
                            <span>Terbitkan invoice otomatis setelah digenerate</span>
                        </label>
                        <div class="card-sub" style="margin-left:23px">
                            Tanpa ini, invoice hasil generate berhenti sebagai draft sampai diterbitkan manual.
                        </div>

                        <label class="checkbox-row" style="margin-top:12px">
                            <input type="checkbox" wire:model.live="values.invoice_auto_send"
                                   @disabled(!($values['invoice_auto_issue'] ?? false))>
                            <span>Kirim email ke pelanggan setelah terbit</span>
                        </label>
                        <div class="card-sub" style="margin-left:23px">
                            @if ($values['invoice_auto_issue'] ?? false)
                                Email dikirim lewat antrean, jadi butuh container <span class="mono">queue</span> berjalan.
                                Pelanggan tanpa alamat email dilewati.
                            @else
                                Hanya bisa diaktifkan bila penerbitan otomatis menyala.
                            @endif
                        </div>

                        @if ($values['invoice_auto_issue'] ?? false)
                            <div class="alert alert-warning" style="margin-top:14px">
                                <strong>Invoice akan langsung ditagihkan tanpa diperiksa manusia.</strong>
                                Sebagai pengaman, invoice yang bermasalah tetap berhenti sebagai draft:
                                meter tanpa pembacaan sepanjang periode, dan stand meter yang mundur
                                (reset/rollover). Keduanya menghasilkan angka yang hampir pasti salah.
                            </div>
                        @endif
                    </div>

                    {{-- ── Kuitansi ────────────────────────────────────── --}}
                    <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border-soft)">
                        <div class="field-label" style="margin-bottom:10px">Kuitansi</div>

                        <div class="field">
                            <label class="field-label">Format Nomor Kuitansi</label>
                            <input type="text" class="input mono @error('values.receipt_number_format') is-invalid @enderror"
                                   wire:model="values.receipt_number_format">
                            @error('values.receipt_number_format') <div class="field-error">{{ $message }}</div> @enderror
                            <div class="card-sub">
                                Penanda yang dikenali: <span class="mono">{YYYY} {YY} {MM} {SEQ}</span>.
                                Nomor urut dihitung per bulan penerbitan.
                            </div>
                        </div>

                        <label class="checkbox-row" style="margin-top:12px">
                            <input type="checkbox" wire:model.live="values.receipt_auto_send">
                            <span>Kirim kuitansi otomatis ke pelanggan</span>
                        </label>
                        <div class="card-sub" style="margin-left:23px">
                            Tanpa ini, kuitansi hanya terkirim bila operator menekan tombol Kirim
                            di halaman Pembayaran.
                        </div>

                        @if ($values['receipt_auto_send'] ?? false)
                            <div class="field" style="margin-top:12px;max-width:260px">
                                <label class="field-label">Kirim Setelah (hari)</label>
                                <input type="number" min="0" max="30"
                                       class="input mono @error('values.receipt_auto_send_days') is-invalid @enderror"
                                       wire:model="values.receipt_auto_send_days">
                                @error('values.receipt_auto_send_days') <div class="field-error">{{ $message }}</div> @enderror
                            </div>

                            <div class="alert alert-info" style="margin-top:12px">
                                Kuitansi dikirim <strong>{{ (int) ($values['receipt_auto_send_days'] ?? 3) }} hari</strong>
                                setelah pembayaran dicatat, bukan seketika. Jeda ini memberi waktu menarik
                                pembayaran yang ternyata salah input — begitu kuitansi terkirim, dokumennya
                                sudah di tangan pelanggan dan batch-nya tidak bisa dibatalkan tanpa izin khusus.
                                Isi <span class="mono">0</span> untuk mengirim di hari yang sama.
                                Pengiriman lewat antrean, jadi butuh container <span class="mono">queue</span> berjalan.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ── IoT ─────────────────────────────────────────────── --}}
                <div class="card">
                    <div class="card-title" style="margin-bottom:16px">Integrasi IoT</div>

                    <div class="field">
                        <label class="field-label">API Token Gateway</label>
                        <div class="input-row">
                            <input type="text" id="api-token-input"
                                   class="input mono @error('values.api_token') is-invalid @enderror"
                                   wire:model="values.api_token"
                                   autocomplete="off" spellcheck="false">
                            <button type="button" class="btn-icon" title="Salin token" aria-label="Salin token"
                                    onclick="
                                        var el = document.getElementById('api-token-input');
                                        if (!el.value) return;
                                        navigator.clipboard.writeText(el.value).then(function () {
                                            window.Toast && Toast.success('Token disalin ke clipboard.');
                                        }, function () {
                                            window.Toast && Toast.error('Gagal menyalin — pilih teksnya lalu salin manual (Ctrl/Cmd+C).');
                                        });
                                    ">
                                <i data-lucide="copy" style="width:15px;height:15px"></i>
                            </button>
                        </div>
                        @error('values.api_token') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">
                            Dipakai seluruh gateway lewat header <span class="mono">X-Api-Token</span>.
                            Bisa digenerate otomatis atau ditulis sendiri — token tetap terlihat kapan saja,
                            tidak disembunyikan setelah dibuat.
                        </div>

                        @can('setting.manage')
                            <button type="button" class="btn btn-outline btn-sm" style="margin-top:10px"
                                    x-on:click="ConfirmDialog.show({
                                            title: 'Buat token baru?',
                                            text: 'Seluruh gateway harus dikonfigurasi ulang, atau kirimannya akan ditolak.',
                                            danger: true,
                                            confirmText: 'Ya, Buat Token Baru',
                                            onConfirm: () => $wire.regenerateToken(),
                                        })">
                                <i data-lucide="key-round" style="width:14px;height:14px"></i>
                                Generate Token Baru
                            </button>
                        @endcan

                        @if (blank($values['api_token'] ?? null))
                            <div class="alert alert-danger" style="margin-top:12px">
                                <strong>Token kosong — endpoint terbuka tanpa autentikasi.</strong>
                                Siapa pun yang bisa menjangkau server dapat mengirim stand kWh palsu dan
                                mengubah tagihan pelanggan. Hanya biarkan kosong bila server benar-benar
                                tertutup di jaringan internal.
                            </div>
                        @endif
                    </div>

                    <div class="field">
                        <label class="field-label">Interval Push Gateway (detik) <span style="color:var(--danger)">*</span></label>
                        <input type="number" min="1" class="input mono @error('values.iot_push_interval_seconds') is-invalid @enderror"
                               wire:model="values.iot_push_interval_seconds">
                        @error('values.iot_push_interval_seconds') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Meter Offline Setelah (menit) <span style="color:var(--danger)">*</span></label>
                        <input type="number" min="1" class="input mono @error('values.iot_offline_after_minutes') is-invalid @enderror"
                               wire:model="values.iot_offline_after_minutes">
                        @error('values.iot_offline_after_minutes') <div class="field-error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label class="field-label">Retensi Data Mentah (bulan) <span style="color:var(--danger)">*</span></label>
                        <input type="number" min="1" class="input mono @error('values.iot_retention_months') is-invalid @enderror"
                               wire:model="values.iot_retention_months">
                        @error('values.iot_retention_months') <div class="field-error">{{ $message }}</div> @enderror
                        <div class="card-sub">
                            Pembacaan mentah yang lebih tua dihapus mingguan. Agregat harian tetap disimpan,
                            jadi riwayat dan laporan lama tidak hilang.
                        </div>
                    </div>

                    {{-- <div class="alert alert-info" style="margin-top:14px">
                        Gateway mengirim data ke <span class="mono">{{ $ingestUrl }}</span>
                        dengan <span class="mono">meter_id</span> pada payload — ID-nya terlihat di
                        halaman Power Meter Device.
                        <a href="{{ $docsUrl }}" target="_blank">Buka dokumentasi API →</a>
                    </div> --}}
                </div>
            </div>
        </div>

        @can('setting.manage')
            <div class="row" style="margin-top:20px">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <i data-lucide="check" style="width:15px;height:15px"></i>
                    <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </button>
            </div>
        @endcan
    </form>

</div>
