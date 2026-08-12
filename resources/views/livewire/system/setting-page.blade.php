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
                </div>

                {{-- ── IoT ─────────────────────────────────────────────── --}}
                <div class="card">
                    <div class="card-title" style="margin-bottom:16px">Integrasi IoT</div>

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

                    <div class="alert alert-info" style="margin-top:14px">
                        Gateway mengirim data ke <span class="mono">{{ url('/api/v1/readings') }}</span>
                        dengan header <span class="mono">X-Device-Key</span> milik masing-masing meter.
                    </div>
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
