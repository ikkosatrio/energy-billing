<?php

namespace Tests\Feature;

use App\Livewire\Billing\PaymentPage;
use App\Mail\ReceiptMail;
use App\Mail\ReceiptVoidedMail;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentBatch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\BulkPaymentService;
use App\Services\Billing\ReceiptService;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kuitansi: terbit per pembayaran (termasuk cicilan), dikirim manual atau
 * terjadwal setelah masa tunggu, dan mengunci pembatalan batch begitu sampai
 * ke pelanggan.
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->actingAs($this->superAdmin());

        Mail::fake();
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    private function setting(string $key, mixed $value): void
    {
        app(SettingService::class)->put($key, $value);
    }

    private function invoice(float $total = 1_000_000, string $no = 'INV-1'): Invoice
    {
        $customer = Customer::create([
            'code' => 'C-'.$no, 'name' => 'PT Pelanggan '.$no,
            'email' => strtolower($no).'@pelanggan.test', 'status' => 'active',
        ]);

        $period = BillingPeriod::firstOrCreate(
            ['code' => '2026-07'],
            ['period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01'],
        );

        return Invoice::create([
            'invoice_no' => $no, 'billing_period_id' => $period->id, 'customer_id' => $customer->id,
            'customer_name' => $customer->name, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01', 'due_date' => '2026-08-20',
            'total_amount' => $total, 'status' => 'issued',
        ]);
    }

    private function pay(Invoice $invoice, float $amount, string $date = '2026-08-15'): InvoicePayment
    {
        return InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => $date,
            'amount' => $amount, 'method' => 'transfer',
        ]);
    }

    // ── Penomoran ────────────────────────────────────────────────────────

    public function test_nomor_kuitansi_mengikuti_format_setting_dan_berurutan(): void
    {
        $receipts = app(ReceiptService::class);

        $satu = $receipts->issue($this->pay($this->invoice(no: 'INV-1'), 500_000));
        $dua = $receipts->issue($this->pay($this->invoice(no: 'INV-2'), 500_000));

        $prefix = 'KW/'.now()->format('Y/m').'/';

        $this->assertSame($prefix.'001', $satu->receipt_no);
        $this->assertSame($prefix.'002', $dua->receipt_no);
    }

    public function test_nomor_hanya_diberikan_sekali(): void
    {
        $receipts = app(ReceiptService::class);
        $payment = $this->pay($this->invoice(), 500_000);

        $first = $receipts->issue($payment)->receipt_no;
        $second = $receipts->issue($payment->refresh())->receipt_no;

        $this->assertSame($first, $second);
    }

    /**
     * Inti dari keputusan menomori saat terbit, bukan saat pembayaran dicatat:
     * pembayaran yang ditarik sebelum kuitansinya keluar tidak boleh
     * meninggalkan lubang di deret nomor.
     */
    public function test_pembayaran_yang_ditarik_sebelum_terbit_tidak_memakan_nomor(): void
    {
        $receipts = app(ReceiptService::class);

        $dibatalkan = $this->pay($this->invoice(no: 'INV-1'), 500_000);
        $dibatalkan->delete();

        $berikutnya = $receipts->issue($this->pay($this->invoice(no: 'INV-2'), 500_000));

        $this->assertSame('KW/'.now()->format('Y/m').'/001', $berikutnya->receipt_no);
    }

    // ── Pembayaran sebagian ──────────────────────────────────────────────

    public function test_tiap_cicilan_mendapat_kuitansi_sendiri_dengan_sisa_yang_benar(): void
    {
        $receipts = app(ReceiptService::class);
        $invoice = $this->invoice(1_000_000);

        $pertama = $receipts->issue($this->pay($invoice, 400_000, '2026-08-10'));
        $kedua = $receipts->issue($this->pay($invoice->refresh(), 600_000, '2026-08-20'));

        $this->assertEquals(400_000, $pertama->receipt_paid_total);
        $this->assertEquals(600_000, $pertama->receipt_outstanding_after);
        $this->assertFalse($pertama->receiptSettlesInvoice());

        $this->assertEquals(1_000_000, $kedua->receipt_paid_total);
        $this->assertEquals(0, $kedua->receipt_outstanding_after);
        $this->assertTrue($kedua->receiptSettlesInvoice());
    }

    /**
     * Regresi: angka kuitansi di-snapshot, bukan dihitung ulang. Pembayaran
     * bertanggal mundur yang diinput belakangan tidak boleh mengubah dokumen
     * yang sudah dipegang pelanggan.
     */
    public function test_angka_kuitansi_tidak_berubah_oleh_pembayaran_berikutnya(): void
    {
        $receipts = app(ReceiptService::class);
        $invoice = $this->invoice(1_000_000);

        $kuitansi = $receipts->issue($this->pay($invoice, 400_000, '2026-08-10'));

        $this->pay($invoice->refresh(), 600_000, '2026-08-01');

        $kuitansi->refresh();

        $this->assertEquals(400_000, $kuitansi->receipt_paid_total);
        $this->assertEquals(600_000, $kuitansi->receipt_outstanding_after);
    }

    public function test_pdf_kuitansi_sebagian_menyebut_sisa_tagihan(): void
    {
        $invoice = $this->invoice(1_000_000);
        $payment = app(ReceiptService::class)->issue($this->pay($invoice, 400_000));

        $html = view('billing.receipts.pdf', [
            'payment' => $payment->load('invoice', 'recordedBy'),
            'invoice' => $invoice->refresh(),
        ])->render();

        $this->assertStringContainsString('PEMBAYARAN SEBAGIAN', $html);
        $this->assertStringContainsString('600.000', $html);
        $this->assertStringNotContainsString('INVOICE LUNAS', $html);
    }

    public function test_pdf_kuitansi_pelunasan_menyebut_lunas(): void
    {
        $invoice = $this->invoice(500_000);
        $payment = app(ReceiptService::class)->issue($this->pay($invoice, 500_000));

        $html = view('billing.receipts.pdf', [
            'payment' => $payment->load('invoice', 'recordedBy'),
            'invoice' => $invoice->refresh(),
        ])->render();

        $this->assertStringContainsString('INVOICE LUNAS', $html);
    }

    // ── Cap pada PDF invoice ─────────────────────────────────────────────

    public function test_pdf_invoice_lunas_bercap_lunas_dan_memuat_riwayat(): void
    {
        $invoice = $this->invoice(500_000);
        app(ReceiptService::class)->issue($this->pay($invoice, 500_000));

        $html = $this->renderInvoicePdf($invoice->refresh());

        $this->assertStringContainsString('LUNAS', $html);
        $this->assertStringContainsString('Riwayat Pembayaran', $html);
    }

    public function test_pdf_invoice_dibayar_sebagian_menyebut_sisanya(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 300_000);

        $html = $this->renderInvoicePdf($invoice->refresh());

        $this->assertStringContainsString('DIBAYAR SEBAGIAN', $html);
        $this->assertStringContainsString('700.000', $html);
    }

    public function test_pdf_invoice_belum_dibayar_tanpa_cap(): void
    {
        $html = $this->renderInvoicePdf($this->invoice());

        $this->assertStringNotContainsString('DIBAYAR SEBAGIAN', $html);
        $this->assertStringNotContainsString('Riwayat Pembayaran', $html);
    }

    private function renderInvoicePdf(Invoice $invoice): string
    {
        $documents = app(\App\Services\Billing\InvoiceDocumentService::class);

        return view('billing.invoices.pdf', [
            'invoice' => $invoice->load('payments'),
            'lines' => $documents->lines($invoice),
            'totals' => $documents->totals($invoice),
        ])->render();
    }

    // ── Pengiriman ───────────────────────────────────────────────────────

    public function test_tombol_kirim_mengirim_kuitansi_dan_menandai_terkirim(): void
    {
        $payment = $this->pay($this->invoice(), 1_000_000);

        Livewire::test(PaymentPage::class)->call('sendReceipt', $payment->id);

        $payment->refresh();

        Mail::assertSent(ReceiptMail::class);
        $this->assertNotNull($payment->receipt_no);
        $this->assertNotNull($payment->receipt_sent_at);
    }

    public function test_kuitansi_invoice_batal_tidak_bisa_dikirim(): void
    {
        $invoice = $this->invoice();
        $payment = $this->pay($invoice, 500_000);
        $invoice->forceFill(['status' => 'cancelled'])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah dibatalkan');

        app(ReceiptService::class)->email($payment);
    }

    public function test_pelanggan_tanpa_email_ditolak(): void
    {
        $invoice = $this->invoice();
        $invoice->customer->forceFill(['email' => null])->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum punya alamat email');

        app(ReceiptService::class)->email($this->pay($invoice->refresh(), 500_000));
    }

    // ── Kirim otomatis H+N ───────────────────────────────────────────────

    public function test_kirim_otomatis_menunggu_masa_tunggu_lewat(): void
    {
        $this->setting('receipt_auto_send', true);
        $this->setting('receipt_auto_send_days', 3);

        $baru = $this->pay($this->invoice(no: 'INV-1'), 500_000);
        $lama = $this->pay($this->invoice(no: 'INV-2'), 500_000);
        $lama->forceFill(['created_at' => now()->subDays(4)])->save();

        $this->artisan('receipts:send-due')->assertSuccessful();

        Mail::assertQueued(ReceiptMail::class, 1);
        $this->assertNull($baru->refresh()->receipt_sent_at);
        $this->assertNotNull($lama->refresh()->receipt_sent_at);
    }

    public function test_kirim_otomatis_dilewati_saat_setelan_mati(): void
    {
        $this->setting('receipt_auto_send', false);

        $payment = $this->pay($this->invoice(), 500_000);
        $payment->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('receipts:send-due')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNull($payment->refresh()->receipt_sent_at);
    }

    public function test_kirim_otomatis_tidak_mengirim_dua_kali(): void
    {
        $this->setting('receipt_auto_send', true);
        $this->setting('receipt_auto_send_days', 0);

        $payment = $this->pay($this->invoice(), 500_000);
        $payment->forceFill(['created_at' => now()->subDay()])->save();

        $this->artisan('receipts:send-due');
        $this->artisan('receipts:send-due');

        Mail::assertQueued(ReceiptMail::class, 1);
    }

    // ── Pembatalan batch vs kuitansi terkirim ────────────────────────────

    private function batchWithSentReceipt(): PaymentBatch
    {
        $batch = PaymentBatch::create(['type' => 'bulk', 'payment_count' => 1, 'total_amount' => 500_000]);

        $payment = $this->pay($this->invoice(), 500_000);
        $payment->forceFill(['payment_batch_id' => $batch->id])->save();

        app(ReceiptService::class)->email($payment);

        return $batch->refresh();
    }

    public function test_batch_dengan_kuitansi_terkirim_menolak_pembatalan_biasa(): void
    {
        $batch = $this->batchWithSentReceipt();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah dikirim ke pelanggan');

        app(BulkPaymentService::class)->revert($batch);
    }

    public function test_pembatalan_paksa_menarik_pembayaran_dan_memberi_tahu_pelanggan(): void
    {
        $batch = $this->batchWithSentReceipt();

        Livewire::test(PaymentPage::class)
            ->call('forceRevertBatch', $batch->id, 'Dana belum masuk rekening');

        Mail::assertQueued(ReceiptVoidedMail::class);
        $this->assertSame(0, InvoicePayment::count());
        $this->assertNotNull($batch->refresh()->reverted_at);
    }

    public function test_pembatalan_paksa_ditolak_tanpa_izin_khusus(): void
    {
        $batch = $this->batchWithSentReceipt();

        $role = Role::create(['name' => 'Bulk Saja', 'slug' => 'bulk-saja']);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['payment.view', 'payment.bulk', 'payment.receipt'])->pluck('id'),
        );

        $this->actingAs(User::create([
            'name' => 'Staff', 'username' => 'staff', 'email' => 'staff@test.local',
            'password' => 'secret123', 'role_id' => $role->id,
        ]));

        Livewire::test(PaymentPage::class)
            ->call('forceRevertBatch', $batch->id, 'coba paksa')
            ->assertForbidden();

        $this->assertSame(1, InvoicePayment::count());
    }

    public function test_batch_tanpa_kuitansi_terkirim_tetap_bisa_dibatalkan_biasa(): void
    {
        $batch = PaymentBatch::create(['type' => 'bulk', 'payment_count' => 1, 'total_amount' => 500_000]);

        $payment = $this->pay($this->invoice(), 500_000);
        $payment->forceFill(['payment_batch_id' => $batch->id])->save();

        Livewire::test(PaymentPage::class)->call('revertBatch', $batch->id);

        Mail::assertNotQueued(ReceiptVoidedMail::class);
        $this->assertSame(0, InvoicePayment::count());
    }

    // ── Izin ─────────────────────────────────────────────────────────────

    public function test_izin_kuitansi_terdaftar(): void
    {
        $this->assertDatabaseHas('permissions', ['slug' => 'payment.receipt']);
        $this->assertDatabaseHas('permissions', ['slug' => 'payment.force_revert']);

        $staff = Role::with('permissions')->where('slug', 'billing-staff')->firstOrFail();

        $this->assertTrue($staff->permissions->contains('slug', 'payment.receipt'));
        // Pembatalan paksa sengaja tidak diberikan ke staff.
        $this->assertFalse($staff->permissions->contains('slug', 'payment.force_revert'));
    }

    public function test_unduh_kuitansi_menerbitkan_nomornya(): void
    {
        $payment = $this->pay($this->invoice(), 500_000);

        $this->get(route('billing.payments.receipt', $payment))->assertOk();

        $this->assertNotNull($payment->refresh()->receipt_no);
    }
}
