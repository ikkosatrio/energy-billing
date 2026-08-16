<?php

namespace Tests\Feature;

use App\Livewire\Billing\InvoicePage;
use App\Livewire\Billing\PaymentPage;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentBatch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\PaymentImportService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pembayaran massal: tandai lunas dari daftar invoice, entri cepat, dan impor
 * berkas — beserta pembatalan batch-nya.
 */
class BulkPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->actingAs($this->superAdmin());
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);
    }

    private function period(): BillingPeriod
    {
        return BillingPeriod::firstOrCreate(
            ['code' => '2026-07'],
            ['period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01'],
        );
    }

    private function invoice(string $no, float $total = 1_000_000, string $status = 'issued'): Invoice
    {
        $customer = Customer::create([
            'code' => 'C-'.$no, 'name' => 'PT Pelanggan '.$no, 'status' => 'active',
        ]);

        return Invoice::create([
            'invoice_no' => $no,
            'billing_period_id' => $this->period()->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01', 'due_date' => '2026-08-20',
            'total_amount' => $total, 'status' => $status,
        ]);
    }

    // ── Opsi A: tandai lunas massal ──────────────────────────────────────

    public function test_beberapa_invoice_dilunasi_sekaligus(): void
    {
        $satu = $this->invoice('INV-1');
        $dua = $this->invoice('INV-2', 750_000);

        Livewire::test(InvoicePage::class)
            ->set('selected', [(string) $satu->id, (string) $dua->id])
            ->call('openBulk')
            ->set('bulkForm.payment_date', '2026-08-15')
            ->set('bulkForm.method', 'transfer')
            ->call('bulkMarkPaid');

        $this->assertSame('paid', $satu->refresh()->status);
        $this->assertSame('paid', $dua->refresh()->status);
        $this->assertEquals(1_750_000, InvoicePayment::sum('amount'));

        $batch = PaymentBatch::firstOrFail();
        $this->assertSame('bulk', $batch->type);
        $this->assertSame(2, $batch->payment_count);
    }

    public function test_yang_dibayar_adalah_sisa_tagihan_bukan_total(): void
    {
        $invoice = $this->invoice('INV-1');

        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-08-10',
            'amount' => 400_000, 'method' => 'transfer',
        ]);

        Livewire::test(InvoicePage::class)
            ->set('selected', [(string) $invoice->id])
            ->call('openBulk')
            ->call('bulkMarkPaid');

        $this->assertSame('paid', $invoice->refresh()->status);
        // 400rb yang sudah ada + 600rb sisa, bukan 400rb + 1jt.
        $this->assertEquals(1_000_000, InvoicePayment::where('invoice_id', $invoice->id)->sum('amount'));
    }

    public function test_invoice_draft_batal_dan_lunas_dilewati_dengan_alasan(): void
    {
        $draft = $this->invoice('INV-1', status: 'draft');
        $batal = $this->invoice('INV-2', status: 'cancelled');
        $lunas = $this->invoice('INV-3', status: 'paid');
        $lunas->forceFill(['paid_amount' => $lunas->total_amount])->save();

        $sehat = $this->invoice('INV-4');

        $component = Livewire::test(InvoicePage::class)
            ->set('selected', array_map('strval', [$draft->id, $batal->id, $lunas->id, $sehat->id]))
            ->call('openBulk')
            ->call('bulkMarkPaid');

        $result = $component->get('bulkResult');

        $this->assertSame(1, $result['created']);
        $this->assertCount(3, $result['skipped']);
        $this->assertSame('draft', $draft->refresh()->status);
        $this->assertSame('cancelled', $batal->refresh()->status);
        $this->assertSame('paid', $sehat->refresh()->status);
    }

    public function test_membatalkan_batch_mengembalikan_status_invoice(): void
    {
        $invoice = $this->invoice('INV-1');

        $component = Livewire::test(InvoicePage::class)
            ->set('selected', [(string) $invoice->id])
            ->call('openBulk')
            ->call('bulkMarkPaid');

        $batchId = $component->get('bulkResult')['batch_id'];
        $component->call('revertBulk', $batchId);

        $invoice->refresh();

        $this->assertSame(0, InvoicePayment::count());
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertNotSame('paid', $invoice->status);
        $this->assertNotNull(PaymentBatch::find($batchId)->reverted_at);
    }

    public function test_batch_yang_sudah_dibatalkan_tidak_bisa_dibatalkan_lagi(): void
    {
        $invoice = $this->invoice('INV-1');

        $component = Livewire::test(InvoicePage::class)
            ->set('selected', [(string) $invoice->id])
            ->call('openBulk')
            ->call('bulkMarkPaid');

        $batchId = $component->get('bulkResult')['batch_id'];

        $component->call('revertBulk', $batchId);
        $component->call('revertBulk', $batchId);

        // Pembatalan kedua tidak boleh menyentuh apa pun.
        $this->assertSame(0, InvoicePayment::count());
    }

    public function test_centangan_dibuang_saat_filter_berubah(): void
    {
        $invoice = $this->invoice('INV-1');

        Livewire::test(InvoicePage::class)
            ->set('selected', [(string) $invoice->id])
            ->set('statusFilter', 'paid')
            ->assertSet('selected', []);
    }

    public function test_bulk_ditolak_tanpa_izin(): void
    {
        $invoice = $this->invoice('INV-1');

        $role = Role::create(['name' => 'Tanpa Bulk', 'slug' => 'tanpa-bulk']);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['invoice.view', 'payment.view', 'payment.create'])->pluck('id'),
        );

        $this->actingAs(User::create([
            'name' => 'Staff', 'username' => 'staff', 'email' => 'staff@test.local',
            'password' => 'secret123', 'role_id' => $role->id,
        ]));

        Livewire::test(InvoicePage::class)
            ->set('selected', [(string) $invoice->id])
            ->call('openBulk')
            ->assertForbidden();

        $this->assertSame(0, InvoicePayment::count());
    }

    // ── Opsi B: entri cepat ──────────────────────────────────────────────

    public function test_entri_cepat_mengisi_sisa_tagihan_otomatis(): void
    {
        $this->invoice('INV-1', 850_000);

        Livewire::test(PaymentPage::class)
            ->set('quickInvoiceNo', 'INV-1')
            ->assertSet('quickAmount', '850000');
    }

    public function test_entri_cepat_menyimpan_dan_mengosongkan_kolom(): void
    {
        $invoice = $this->invoice('INV-1');

        Livewire::test(PaymentPage::class)
            ->set('quickInvoiceNo', 'INV-1')
            ->set('quickDate', '2026-08-15')
            ->call('quickSave')
            ->assertSet('quickInvoiceNo', '')
            // Tanggal dipertahankan supaya entri berikutnya tidak perlu diketik ulang.
            ->assertSet('quickDate', '2026-08-15');

        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_entri_cepat_menolak_nomor_invoice_asing(): void
    {
        Livewire::test(PaymentPage::class)
            ->set('quickInvoiceNo', 'TIDAK-ADA')
            ->call('quickSave');

        $this->assertSame(0, InvoicePayment::count());
    }

    public function test_entri_cepat_menolak_invoice_draft(): void
    {
        $this->invoice('INV-1', status: 'draft');

        Livewire::test(PaymentPage::class)
            ->set('quickInvoiceNo', 'INV-1')
            ->set('quickAmount', '500000')
            ->call('quickSave');

        $this->assertSame(0, InvoicePayment::count());
    }

    // ── Opsi C: impor berkas ─────────────────────────────────────────────

    /** @param array<int, array<int, string>> $rows */
    private function csv(array $rows): UploadedFile
    {
        $lines = [implode(',', PaymentImportService::COLUMNS)];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return UploadedFile::fake()->createWithContent('mutasi.csv', implode("\n", $lines));
    }

    public function test_impor_mencatat_pembayaran_dari_berkas(): void
    {
        $satu = $this->invoice('INV-1');
        $dua = $this->invoice('INV-2', 500_000);

        $file = $this->csv([
            ['2026-08-15', 'INV-1', 'PT Pelanggan INV-1', '1000000', 'transfer', 'TRF01', ''],
            ['2026-08-15', 'INV-2', '', '500000', 'transfer', 'TRF02', ''],
        ]);

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $file)
            ->call('previewImport');

        $this->assertSame(2, $component->get('importSummary')['valid']);
        // Belum ada yang tersimpan sebelum dikonfirmasi.
        $this->assertSame(0, InvoicePayment::count());

        $component->call('commitImport');

        $this->assertSame('paid', $satu->refresh()->status);
        $this->assertSame('paid', $dua->refresh()->status);
        $this->assertSame('import', PaymentBatch::firstOrFail()->type);
    }

    public function test_impor_menolak_baris_tanpa_nomor_invoice(): void
    {
        $this->invoice('INV-1');

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv([
                ['2026-08-15', '', 'PT Pelanggan INV-1', '1000000', 'transfer', '', ''],
            ]))
            ->call('previewImport');

        $rows = $component->get('importRows');

        $this->assertFalse($rows[0]['ok']);
        $this->assertStringContainsString('Nomor invoice wajib', $rows[0]['error']);
    }

    public function test_impor_menolak_nama_pelanggan_yang_tidak_cocok(): void
    {
        $this->invoice('INV-1');
        $this->invoice('INV-2');

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv([
                ['2026-08-15', 'INV-1', 'PT Pelanggan INV-2', '1000000', 'transfer', '', ''],
            ]))
            ->call('previewImport');

        $rows = $component->get('importRows');

        $this->assertFalse($rows[0]['ok']);
        $this->assertStringContainsString('tidak cocok', $rows[0]['error']);
    }

    public function test_impor_menolak_jumlah_melebihi_sisa_tagihan(): void
    {
        $this->invoice('INV-1', 500_000);

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv([
                ['2026-08-15', 'INV-1', '', '900000', 'transfer', '', ''],
            ]))
            ->call('previewImport');

        $this->assertFalse($component->get('importRows')[0]['ok']);
    }

    public function test_impor_menolak_baris_kembar_di_berkas_yang_sama(): void
    {
        $this->invoice('INV-1');

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv([
                ['2026-08-15', 'INV-1', '', '500000', 'transfer', 'TRF01', ''],
                ['2026-08-15', 'INV-1', '', '500000', 'transfer', 'TRF01', ''],
            ]))
            ->call('previewImport');

        $rows = $component->get('importRows');

        $this->assertTrue($rows[0]['ok']);
        $this->assertFalse($rows[1]['ok']);
        $this->assertStringContainsString('Sama persis dengan baris', $rows[1]['error']);
    }

    /**
     * Regresi: berkas mutasi yang tanpa sengaja diunggah dua kali pernah
     * menjadi kekhawatiran utama fitur ini.
     */
    public function test_berkas_yang_sama_tidak_bisa_diimpor_dua_kali(): void
    {
        $invoice = $this->invoice('INV-1');

        $baris = [['2026-08-15', 'INV-1', '', '1000000', 'transfer', 'TRF01', '']];

        Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv($baris))
            ->call('previewImport')
            ->call('commitImport');

        $this->assertSame(1, InvoicePayment::count());

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv($baris))
            ->call('previewImport');

        $rows = $component->get('importRows');

        $this->assertFalse($rows[0]['ok']);
        $this->assertStringContainsString('sudah pernah diimpor', $rows[0]['error']);
        $this->assertSame(1, InvoicePayment::count());
        $this->assertEquals(1_000_000, $invoice->refresh()->paid_amount);
    }

    public function test_impor_menerima_nominal_berpemisah_ribuan(): void
    {
        $this->invoice('INV-1');

        // Kutip ganda supaya "1.000.000" tidak terpecah oleh koma CSV.
        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv([
                ['2026-08-15', 'INV-1', '', '"1.000.000"', 'transfer', '', ''],
            ]))
            ->call('previewImport');

        $rows = $component->get('importRows');

        $this->assertTrue($rows[0]['ok'], $rows[0]['error'] ?? '');
        $this->assertEquals(1_000_000, $rows[0]['amount']);
    }

    public function test_membatalkan_batch_impor_menarik_seluruh_pembayarannya(): void
    {
        $satu = $this->invoice('INV-1');
        $this->invoice('INV-2', 500_000);

        $component = Livewire::test(PaymentPage::class)
            ->set('importFile', $this->csv([
                ['2026-08-15', 'INV-1', '', '1000000', 'transfer', 'TRF01', ''],
                ['2026-08-15', 'INV-2', '', '500000', 'transfer', 'TRF02', ''],
            ]))
            ->call('previewImport')
            ->call('commitImport');

        $component->call('revertBatch', $component->get('importResult')['batch_id']);

        $this->assertSame(0, InvoicePayment::count());
        $this->assertEquals(0, $satu->refresh()->paid_amount);
    }

    public function test_template_bisa_diunduh(): void
    {
        $this->get(route('billing.payments.template'))->assertOk();
    }

    public function test_permission_bulk_terdaftar_dan_dimiliki_billing_staff(): void
    {
        $this->assertDatabaseHas('permissions', ['slug' => 'payment.bulk']);

        $staff = Role::with('permissions')->where('slug', 'billing-staff')->firstOrFail();

        $this->assertTrue($staff->permissions->contains('slug', 'payment.bulk'));
        // Justru inti pilihannya: staff bisa menarik kembali batch-nya sendiri
        // tanpa perlu diberi hak menghapus pembayaran satuan.
        $this->assertFalse($staff->permissions->contains('slug', 'payment.delete'));
    }
}
