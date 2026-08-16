<?php

namespace Tests\Feature;

use App\Livewire\Report\PaymentReportPage;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Role;
use App\Models\User;
use App\Services\Report\ReportService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Laporan Pembayaran — satu baris per transaksi (bukan per invoice), plus
 * ringkasan tunggakan (aging + invoice sebagian) yang independen dari
 * filter tanggal bayar.
 */
class PaymentReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->actingAs(User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]));
    }

    private static int $seq = 0;

    private function invoice(float $total, string $customerName = 'PT Contoh', ?string $dueDate = '2026-08-15'): Invoice
    {
        self::$seq++;

        $customer = Customer::create(['code' => 'C-'.self::$seq, 'name' => $customerName, 'status' => 'active']);

        $period = BillingPeriod::firstOrCreate(
            ['code' => '2026-07'],
            ['period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01'],
        );

        return Invoice::create([
            'invoice_no' => 'INV/2026/07/'.str_pad(self::$seq, 3, '0', STR_PAD_LEFT),
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01', 'due_date' => $dueDate,
            'subtotal' => $total, 'total_amount' => $total,
            'status' => 'issued',
        ]);
    }

    private function pay(Invoice $invoice, float $amount, string $date, string $method = 'transfer'): InvoicePayment
    {
        return InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => $date,
            'amount' => $amount, 'method' => $method,
        ]);
    }

    // ── Transaksi & filter ───────────────────────────────────────────────

    public function test_transaksi_dalam_rentang_tanggal_bayar_ikut_terhitung(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 1_000_000, '2026-08-05');
        $this->pay($this->invoice(500_000), 500_000, '2026-09-05'); // di luar rentang

        $rows = app(ReportService::class)->payments(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertCount(1, $rows);
        $this->assertEquals(1_000_000, $rows[0]['amount']);
    }

    public function test_filter_metode_hanya_menampilkan_metode_terpilih(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 400_000, '2026-08-05', 'transfer');
        $this->pay($invoice, 600_000, '2026-08-06', 'cash');

        $rows = app(ReportService::class)->payments(
            Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), method: 'cash',
        );

        $this->assertCount(1, $rows);
        $this->assertSame('cash', $rows[0]['method']);
    }

    public function test_filter_hanya_sebagian_mengecualikan_invoice_lunas(): void
    {
        $lunas = $this->invoice(500_000);
        $this->pay($lunas, 500_000, '2026-08-05');

        $sebagian = $this->invoice(1_000_000);
        $this->pay($sebagian, 400_000, '2026-08-06');

        $rows = app(ReportService::class)->payments(
            Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), partialOnly: true,
        );

        $this->assertCount(1, $rows);
        $this->assertSame($sebagian->invoice_no, $rows[0]['invoice_no']);
    }

    public function test_transaksi_membawa_sisa_invoice_saat_ini(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 400_000, '2026-08-05');

        $rows = app(ReportService::class)->payments(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertSame('partial', $rows[0]['invoice_status']);
        $this->assertEquals(600_000, $rows[0]['invoice_outstanding']);
    }

    public function test_sumber_pembayaran_manual_dibedakan_dari_batch(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 1_000_000, '2026-08-05');

        $rows = app(ReportService::class)->payments(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

        $this->assertSame('Manual', $rows[0]['source']);
    }

    // ── Ringkasan tunggakan (aging) ──────────────────────────────────────

    public function test_aging_mengelompokkan_berdasarkan_hari_jatuh_tempo(): void
    {
        Carbon::setTestNow('2026-09-15');

        $this->invoice(1_000_000, dueDate: '2026-09-20');   // belum jatuh tempo
        $this->invoice(2_000_000, dueDate: '2026-09-01');   // 14 hari (1-30)
        $this->invoice(3_000_000, dueDate: '2026-08-01');   // 45 hari (31-60)
        $this->invoice(4_000_000, dueDate: '2026-06-01');   // > 60 hari

        $tracking = app(ReportService::class)->paymentTracking();

        $this->assertSame(1, $tracking['aging']['current']['count']);
        $this->assertSame(1, $tracking['aging']['d1_30']['count']);
        $this->assertSame(1, $tracking['aging']['d31_60']['count']);
        $this->assertSame(1, $tracking['aging']['d60_plus']['count']);
        $this->assertEquals(4_000_000, $tracking['aging']['d60_plus']['amount']);

        Carbon::setTestNow();
    }

    public function test_invoice_lunas_tidak_ikut_aging(): void
    {
        Carbon::setTestNow('2026-09-15');

        $lunas = $this->invoice(1_000_000, dueDate: '2026-06-01');
        $this->pay($lunas, 1_000_000, '2026-08-01');

        $tracking = app(ReportService::class)->paymentTracking();

        $totalCount = collect($tracking['aging'])->sum('count');
        $this->assertSame(0, $totalCount);

        Carbon::setTestNow();
    }

    public function test_ringkasan_sebagian_menjumlahkan_sisa_bukan_total_tagihan(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 700_000, '2026-08-05');

        $tracking = app(ReportService::class)->paymentTracking();

        $this->assertSame(1, $tracking['partial']['count']);
        $this->assertEquals(300_000, $tracking['partial']['amount']);
    }

    // ── Halaman & export ─────────────────────────────────────────────────

    public function test_halaman_laporan_pembayaran_terbuka(): void
    {
        $this->get(route('report.payments'))
            ->assertOk()
            ->assertSeeLivewire(PaymentReportPage::class);
    }

    public function test_export_excel_laporan_pembayaran(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 1_000_000, '2026-08-05');

        $this->get(route('report.export', ['type' => 'payments', 'format' => 'xlsx'])
            .'?from=2026-08-01&to=2026-08-31')
            ->assertOk();
    }

    public function test_export_pdf_laporan_pembayaran(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 1_000_000, '2026-08-05');

        $this->get(route('report.export', ['type' => 'payments', 'format' => 'pdf'])
            .'?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_komponen_livewire_menghitung_total_per_metode(): void
    {
        $invoice = $this->invoice(1_000_000);
        $this->pay($invoice, 400_000, '2026-08-05', 'transfer');
        $this->pay($invoice, 300_000, '2026-08-06', 'cash');
        $this->pay($invoice, 300_000, '2026-08-07', 'other');

        Livewire::test(PaymentReportPage::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31')
            ->assertViewHas('methodTotals', fn ($totals) => $totals['transfer'] === 400_000.0
                && $totals['cash'] === 300_000.0
                && $totals['other'] === 300_000.0);
    }
}
