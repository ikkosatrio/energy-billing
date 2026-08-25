<?php

namespace Tests\Feature;

use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Batas akses portal pelanggan — bagian yang kalau bocor paling berbahaya.
 *
 * Dua hal diuji terpisah karena keduanya bisa gagal sendiri-sendiri:
 *
 *   isolasi guard  — akun portal tidak boleh menembus panel staf sama sekali.
 *   batas data     — akun portal hanya boleh melihat pelanggan yang di-assign,
 *                    termasuk ketika id di URL atau di properti Livewire
 *                    diganti secara sengaja.
 *
 * Catatan: pemeriksaan seperti ini TIDAK bisa diandalkan lewat browser biasa,
 * karena guard staf dan guard portal berbagi satu cookie session — sesi staf
 * yang masih hidup membuat halaman admin tetap terbuka dan hasilnya terbaca
 * seolah portal-nya bocor. Setiap test di sini memakai sesi yang bersih.
 */
class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    private PowerMeter $meterMilik;

    private PowerMeter $meterOrangLain;

    private Customer $milik;

    private Customer $orangLain;

    /** Pembeda periode antar invoice dalam satu test. */
    private int $periodeKe = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->periodeKe = 0;

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        Carbon::setTestNow('2026-08-15 09:00:00');

        $this->meterMilik = PowerMeter::create(['code' => 'MTR-MILIK', 'name' => 'Panel Milik', 'multiplier' => 1, 'status' => 'active']);
        $this->meterOrangLain = PowerMeter::create(['code' => 'MTR-LAIN', 'name' => 'Panel Lain', 'multiplier' => 1, 'status' => 'active']);

        $this->milik = Customer::create([
            'code' => 'CUST-MILIK', 'name' => 'Pelanggan Milik',
            'address' => 'Jl. Milik', 'power_meter_id' => $this->meterMilik->id,
        ]);
        $this->orangLain = Customer::create([
            'code' => 'CUST-LAIN', 'name' => 'Pelanggan Lain',
            'address' => 'Jl. Lain', 'power_meter_id' => $this->meterOrangLain->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function akun(string $roleSlug = 'portal-penuh', ?Customer $akses = null): CustomerUser
    {
        $akun = CustomerUser::create([
            'name' => 'Budi Pelanggan',
            'username' => 'budi',
            'password' => 'rahasia123',
            'role_id' => Role::portal()->where('slug', $roleSlug)->value('id'),
        ]);

        $akun->customers()->attach(($akses ?? $this->milik)->id);

        return $akun;
    }

    /**
     * Satu invoice, dengan periode sendiri per pemanggilan.
     *
     * `invoices` punya unique(billing_period_id, customer_id) — satu pelanggan
     * hanya boleh punya satu invoice per periode. Jadi tiap invoice di test
     * ini butuh periodenya sendiri, bukan periode yang sama dipakai berulang.
     */
    private function invoiceUntuk(Customer $customer, string $no, string $status = 'issued'): Invoice
    {
        // Bulan mundur satu per invoice: kolom `code` pendek (format Y-m),
        // jadi tidak bisa memakai nomor invoice sebagai penanda periode.
        $bulan = Carbon::parse('2026-07-01')->subMonths($this->periodeKe++);

        $period = BillingPeriod::firstOrCreate(
            ['code' => $bulan->format('Y-m')],
            [
                'period_start' => $bulan->copy()->startOfMonth()->toDateString(),
                'period_end' => $bulan->copy()->endOfMonth()->toDateString(),
                'cut_off_date' => $bulan->copy()->addMonth()->startOfMonth()->toDateString(),
            ],
        );

        return Invoice::create([
            'invoice_no' => $no,
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'power_meter_id' => $customer->power_meter_id,
            'customer_name' => $customer->name,
            'customer_address' => $customer->address,
            'meter_code' => $customer->powerMeter?->code,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-25',
            'total_amount' => 1_000_000,
            'status' => $status,
        ]);
    }

    // ── Isolasi guard: portal tidak menembus panel staf ──────────────────

    /** @dataProvider halamanStaf */
    public function test_akun_portal_tidak_bisa_membuka_halaman_staf(string $route): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        // Ditolak, bukan ditampilkan. 200 di sini berarti akun luar bisa
        // membaca panel admin.
        $response = $this->get($route);

        $this->assertNotSame(200, $response->status(),
            "Akun portal tidak boleh mendapat 200 pada {$route}.");
    }

    public static function halamanStaf(): array
    {
        return [
            'dashboard staf' => ['/dashboard'],
            'user management' => ['/system/users'],
            'setting aplikasi' => ['/system/settings'],
            'master pelanggan' => ['/master/customers'],
            'daftar invoice staf' => ['/billing/invoices'],
            'monitoring staf' => ['/monitoring/realtime'],
            'laporan pemakaian' => ['/report/usage'],
        ];
    }

    public function test_akun_staf_tidak_bisa_membuka_halaman_portal(): void
    {
        $staf = User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);

        // Super admin sekalipun bukan akun portal — guard-nya beda, jadi
        // diarahkan ke login portal, bukan diloloskan.
        $this->actingAs($staf);

        $this->get('/portal')->assertRedirect(route('portal.login'));
    }

    public function test_tanpa_login_diarahkan_ke_login_portal_bukan_login_staf(): void
    {
        // Sebelum perbaikan Authenticate::redirectTo(), pengunjung portal
        // dilempar ke form login staf tempat ia tidak punya akun.
        $this->get('/portal')->assertRedirect(route('portal.login'));
        $this->get('/portal/invoice')->assertRedirect(route('portal.login'));
    }

    // ── Batas data: hanya pelanggan yang di-assign ───────────────────────

    public function test_unduh_invoice_pelanggan_lain_ditolak(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $punyaSendiri = $this->invoiceUntuk($this->milik, 'INV-MILIK');
        $punyaOrangLain = $this->invoiceUntuk($this->orangLain, 'INV-LAIN');

        $this->get(route('portal.invoices.download', $punyaSendiri))->assertOk();
        // Mengganti satu angka di URL tidak boleh cukup untuk membaca tagihan
        // pelanggan lain.
        $this->get(route('portal.invoices.download', $punyaOrangLain))->assertForbidden();
        $this->get(route('portal.invoices.preview', $punyaOrangLain))->assertForbidden();
    }

    /**
     * @dataProvider statusTersembunyi
     */
    public function test_invoice_yang_disembunyikan_tidak_bisa_diunduh(string $status): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $invoice = $this->invoiceUntuk($this->milik, 'INV-'.strtoupper($status), $status);

        // Aturannya sama dengan daftar: yang tidak tampil di daftar juga tidak
        // boleh keluar lewat URL langsung.
        $this->get(route('portal.invoices.download', $invoice))->assertNotFound();
        $this->get(route('portal.invoices.preview', $invoice))->assertNotFound();
    }

    public static function statusTersembunyi(): array
    {
        return [
            // Belum resmi ditagihkan, angkanya masih bisa berubah.
            'draft' => ['draft'],
            // Bukan tagihan yang berlaku.
            'dibatalkan' => ['cancelled'],
        ];
    }

    public function test_daftar_invoice_hanya_memuat_tagihan_yang_berlaku_milik_sendiri(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $this->invoiceUntuk($this->milik, 'INV-MILIK');
        $this->invoiceUntuk($this->milik, 'INV-DRAFT-MILIK', 'draft');
        $this->invoiceUntuk($this->milik, 'INV-BATAL-MILIK', 'cancelled');
        $this->invoiceUntuk($this->orangLain, 'INV-LAIN');

        Livewire::test(\App\Livewire\Portal\InvoicePage::class)
            ->assertSee('INV-MILIK')
            ->assertDontSee('INV-DRAFT-MILIK')
            ->assertDontSee('INV-BATAL-MILIK')
            ->assertDontSee('INV-LAIN');
    }

    public function test_filter_status_tidak_menawarkan_draft_maupun_dibatalkan(): void
    {
        $pilihan = \App\Livewire\Portal\InvoicePage::statusOptions();

        $this->assertNotContains('draft', $pilihan);
        $this->assertNotContains('cancelled', $pilihan);
        // Yang berlaku sebagai tagihan tetap bisa disaring.
        $this->assertEqualsCanonicalizing(['issued', 'partial', 'paid', 'overdue'], $pilihan);
    }

    public function test_membuka_rincian_invoice_yang_disembunyikan_ditolak(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $batal = $this->invoiceUntuk($this->milik, 'INV-BATAL', 'cancelled');
        $draft = $this->invoiceUntuk($this->milik, 'INV-DRAFT', 'draft');

        // Miliknya sendiri, tapi tetap tidak boleh dibuka.
        Livewire::test(\App\Livewire\Portal\InvoicePage::class)
            ->call('show', $batal->id)
            ->assertForbidden();

        Livewire::test(\App\Livewire\Portal\InvoicePage::class)
            ->call('show', $draft->id)
            ->assertForbidden();
    }

    public function test_ringkasan_tidak_menghitung_invoice_yang_disembunyikan(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $this->invoiceUntuk($this->milik, 'INV-BERLAKU');
        $this->invoiceUntuk($this->milik, 'INV-DRAFT', 'draft');
        $this->invoiceUntuk($this->milik, 'INV-BATAL', 'cancelled');

        // Hanya satu invoice yang berlaku, jadi tunggakannya satu × 1.000.000.
        Livewire::test(\App\Livewire\Portal\InvoicePage::class)
            ->assertViewHas('summary', fn ($summary) => $summary['unpaid_count'] === 1
                && (int) $summary['outstanding'] === 1_000_000);
    }

    public function test_pembayaran_atas_invoice_yang_disembunyikan_tidak_tampil(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $batal = $this->invoiceUntuk($this->milik, 'INV-BATAL', 'cancelled');
        $berlaku = $this->invoiceUntuk($this->milik, 'INV-BERLAKU');

        InvoicePayment::create([
            'invoice_id' => $batal->id, 'payment_date' => '2026-08-05',
            'amount' => 999_999, 'method' => 'transfer', 'reference_no' => 'REF-BATAL',
        ]);
        InvoicePayment::create([
            'invoice_id' => $berlaku->id, 'payment_date' => '2026-08-06',
            'amount' => 111_111, 'method' => 'transfer', 'reference_no' => 'REF-BERLAKU',
        ]);

        // Nomor invoice yang disembunyikan di halaman invoice tidak boleh
        // muncul lewat halaman pembayaran.
        Livewire::test(\App\Livewire\Portal\PaymentPage::class)
            ->assertSee('REF-BERLAKU')
            ->assertDontSee('REF-BATAL')
            ->assertDontSee('INV-BATAL')
            ->assertViewHas('summary', fn ($summary) => $summary['count'] === 1);
    }

    public function test_membuka_rincian_invoice_pelanggan_lain_ditolak(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $punyaOrangLain = $this->invoiceUntuk($this->orangLain, 'INV-LAIN');

        // Aksi Livewire dipanggil lewat endpoint sendiri, di luar middleware
        // route — jadi harus punya pemeriksaannya sendiri.
        Livewire::test(\App\Livewire\Portal\InvoicePage::class)
            ->call('show', $punyaOrangLain->id)
            ->assertForbidden();
    }

    public function test_kuitansi_pelanggan_lain_ditolak(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $invoiceLain = $this->invoiceUntuk($this->orangLain, 'INV-LAIN');
        $bayarLain = InvoicePayment::create([
            'invoice_id' => $invoiceLain->id, 'payment_date' => '2026-08-05',
            'amount' => 500_000, 'method' => 'transfer',
            'receipt_no' => 'KW/2026/08/999', 'receipt_issued_at' => now(),
        ]);

        $this->get(route('portal.payments.receipt', $bayarLain))->assertForbidden();
    }

    /**
     * @dataProvider statusTersembunyi
     */
    public function test_kuitansi_atas_invoice_yang_disembunyikan_tidak_bisa_diunduh(string $status): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $invoice = $this->invoiceUntuk($this->milik, 'INV-'.strtoupper($status), $status);
        $bayar = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-08-05',
            'amount' => 500_000, 'method' => 'transfer',
            'receipt_no' => 'KW/2026/08/'.$status, 'receipt_issued_at' => now(),
        ]);

        // Menyembunyikan barisnya dari tabel tidak menutup URL-nya: kuitansi
        // ini tetap bisa diunduh dengan menebak satu angka kalau statusnya
        // tidak ikut diperiksa di controller.
        $this->get(route('portal.payments.receipt', $bayar))->assertNotFound();
        $this->get(route('portal.payments.receipt.preview', $bayar))->assertNotFound();
    }

    public function test_kuitansi_yang_belum_diterbitkan_tidak_bisa_diunduh(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $invoice = $this->invoiceUntuk($this->milik, 'INV-MILIK');
        $bayar = InvoicePayment::create([
            'invoice_id' => $invoice->id, 'payment_date' => '2026-08-05',
            'amount' => 500_000, 'method' => 'transfer',
        ]);

        // ReceiptService::pdf() memberi nomor kuitansi saat pertama diakses.
        // Kalau jalur ini terbuka, klik pelanggan yang menentukan urutan nomor
        // dokumen akuntansi.
        $this->get(route('portal.payments.receipt', $bayar))->assertNotFound();
        $this->assertNull($bayar->fresh()->receipt_no);
    }

    public function test_daftar_pembayaran_hanya_memuat_milik_sendiri(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $invoiceMilik = $this->invoiceUntuk($this->milik, 'INV-MILIK');
        $invoiceLain = $this->invoiceUntuk($this->orangLain, 'INV-LAIN');

        InvoicePayment::create([
            'invoice_id' => $invoiceMilik->id, 'payment_date' => '2026-08-05',
            'amount' => 111_111, 'method' => 'transfer', 'reference_no' => 'REF-MILIK',
        ]);
        InvoicePayment::create([
            'invoice_id' => $invoiceLain->id, 'payment_date' => '2026-08-06',
            'amount' => 222_222, 'method' => 'transfer', 'reference_no' => 'REF-LAIN',
        ]);

        Livewire::test(\App\Livewire\Portal\PaymentPage::class)
            ->assertSee('REF-MILIK')
            ->assertDontSee('REF-LAIN');
    }

    /**
     * Invoice draft yang punya pembayaran memang tidak bisa ada.
     *
     * Selain ketiga jalur pencatatan pembayaran di sisi staf yang menolak
     * invoice draft, ada penjaga yang lebih dalam: InvoicePayment::booted()
     * memanggil refreshPaymentStatus(), dan begitu ada pembayaran masuk
     * statusnya naik ke `partial`. Jadi tidak ada kondisi di mana halaman
     * pembayaran portal menampilkan nomor invoice yang belum ditagihkan —
     * bukan karena disaring, tapi karena barisnya tidak mungkin terbentuk.
     *
     * Test ini mengunci invarian itu. Kalau suatu saat refreshPaymentStatus()
     * berubah dan draft bisa bertahan draft walau sudah dibayar, filter di
     * PaymentPage::ownedQuery() yang menjadi penahannya.
     */
    public function test_pembayaran_menaikkan_invoice_draft_keluar_dari_draft(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $draft = $this->invoiceUntuk($this->milik, 'INV-DRAFT', 'draft');

        InvoicePayment::create([
            'invoice_id' => $draft->id, 'payment_date' => '2026-08-05',
            'amount' => 400_000, 'method' => 'transfer', 'reference_no' => 'REF-BAYAR',
        ]);

        // Sudah menerima uang, jadi bukan draft lagi.
        $this->assertSame('partial', $draft->fresh()->status);

        // Karena statusnya sudah bukan draft, invoice-nya memang layak tampil.
        Livewire::test(\App\Livewire\Portal\InvoicePage::class)
            ->assertSee('INV-DRAFT');
    }

    /**
     * Penjaga di PaymentPage: pembayaran atas invoice yang statusnya draft
     * tidak ikut tampil maupun dijumlahkan.
     *
     * Statusnya dipaksa kembali ke draft SETELAH pembayaran dibuat, karena
     * lewat jalur normal kondisi ini tidak bisa dicapai (lihat test di atas).
     * Yang diuji di sini bukan alur nyata, melainkan bahwa portal tidak
     * bergantung pada invarian yang ditegakkan di tempat lain.
     */
    public function test_pembayaran_atas_invoice_draft_tidak_tampil_di_portal(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $draft = $this->invoiceUntuk($this->milik, 'INV-DRAFT');
        $terbit = $this->invoiceUntuk($this->milik, 'INV-TERBIT');

        InvoicePayment::create([
            'invoice_id' => $draft->id, 'payment_date' => '2026-08-05',
            'amount' => 777_777, 'method' => 'transfer', 'reference_no' => 'REF-DRAFT',
        ]);
        InvoicePayment::create([
            'invoice_id' => $terbit->id, 'payment_date' => '2026-08-06',
            'amount' => 111_111, 'method' => 'transfer', 'reference_no' => 'REF-TERBIT',
        ]);

        $draft->forceFill(['status' => 'draft'])->save();

        Livewire::test(\App\Livewire\Portal\PaymentPage::class)
            ->assertSee('REF-TERBIT')
            ->assertDontSee('REF-DRAFT')
            ->assertDontSee('INV-DRAFT')
            // Totalnya juga tidak boleh ikut menjumlahkan pembayaran itu.
            ->assertViewHas('summary', fn ($summary) => (int) $summary['total'] === 111_111
                && $summary['count'] === 1);
    }

    public function test_riwayat_energi_menolak_meter_pelanggan_lain(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        // meterId adalah properti publik Livewire — bisa diubah dari browser,
        // jadi tidak boleh dipercaya sebagai batas akses.
        Livewire::test(\App\Livewire\Portal\HistoryPage::class)
            ->assertSet('meterId', $this->meterMilik->id)
            ->set('meterId', $this->meterOrangLain->id)
            ->assertForbidden();
    }

    public function test_monitoring_hanya_menampilkan_meter_sendiri(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        Livewire::test(\App\Livewire\Portal\MonitoringPage::class)
            ->assertSee('Panel Milik')
            ->assertDontSee('Panel Lain');
    }

    public function test_rekap_pemakaian_hanya_menjumlahkan_meter_sendiri(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        MeterReadingDaily::create([
            'power_meter_id' => $this->meterMilik->id, 'date' => '2026-08-01',
            'stand_lwbp_start' => 0, 'stand_lwbp_end' => 100, 'stand_wbp_start' => 0, 'stand_wbp_end' => 20,
            'kwh_lwbp' => 100, 'kwh_wbp' => 20, 'reading_count' => 48, 'reset_count' => 0,
        ]);
        MeterReadingDaily::create([
            'power_meter_id' => $this->meterOrangLain->id, 'date' => '2026-08-01',
            'stand_lwbp_start' => 0, 'stand_lwbp_end' => 999, 'stand_wbp_start' => 0, 'stand_wbp_end' => 999,
            'kwh_lwbp' => 999, 'kwh_wbp' => 999, 'reading_count' => 48, 'reset_count' => 0,
        ]);

        Livewire::test(\App\Livewire\Portal\UsagePage::class)
            ->set('from', '2026-08-01')
            ->set('to', '2026-08-31')
            ->assertSee('Pelanggan Milik')
            ->assertDontSee('Pelanggan Lain')
            ->assertViewHas('totals', fn ($totals) => (int) $totals['total_kwh'] === 120);
    }

    // ── Permission portal menentukan halaman mana yang terbuka ───────────

    public function test_role_pantau_saja_tidak_bisa_membuka_invoice_dan_pembayaran(): void
    {
        $this->actingAs($this->akun('portal-pantau'), CustomerUser::GUARD);

        $this->get('/portal/monitoring')->assertOk();
        $this->get('/portal/pemakaian')->assertOk();

        $this->get('/portal/invoice')->assertForbidden();
        $this->get('/portal/pembayaran')->assertForbidden();
    }

    public function test_role_pantau_saja_ditolak_walau_memanggil_komponen_langsung(): void
    {
        $this->actingAs($this->akun('portal-pantau'), CustomerUser::GUARD);

        // Route-nya sudah ditolak, tapi komponennya punya endpoint sendiri.
        Livewire::test(\App\Livewire\Portal\InvoicePage::class)->assertForbidden();
        Livewire::test(\App\Livewire\Portal\PaymentPage::class)->assertForbidden();
    }

    public function test_role_pantau_saja_tidak_melihat_nominal_tagihan_di_dashboard(): void
    {
        $this->actingAs($this->akun('portal-pantau'), CustomerUser::GUARD);

        $this->invoiceUntuk($this->milik, 'INV-MILIK');

        Livewire::test(\App\Livewire\Portal\DashboardPage::class)
            ->assertDontSee('Tagihan Belum Lunas')
            ->assertViewHas('tagihan', fn ($tagihan) => $tagihan['outstanding'] === 0.0);
    }

    // ── Login ───────────────────────────────────────────────────────────

    public function test_login_portal_berhasil_dan_mencatat_waktu_masuk(): void
    {
        $akun = $this->akun();

        $this->post(route('portal.login'), [
            'username' => 'budi',
            'password' => 'rahasia123',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($akun, CustomerUser::GUARD);
        $this->assertNotNull($akun->fresh()->last_login_at);
    }

    public function test_login_portal_dengan_kredensial_staf_ditolak(): void
    {
        User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);

        // Tabel dan guard-nya terpisah, jadi kredensial staf memang tidak
        // dikenali di portal — bukan sekadar tidak diberi izin.
        $this->post(route('portal.login'), [
            'username' => 'admin',
            'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest(CustomerUser::GUARD);
    }

    public function test_akun_nonaktif_ditolak_login(): void
    {
        $akun = $this->akun();
        $akun->forceFill(['is_active' => false])->save();

        $this->post(route('portal.login'), [
            'username' => 'budi',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest(CustomerUser::GUARD);
    }

    public function test_akun_tanpa_pelanggan_ditolak_dengan_alasan_jelas(): void
    {
        $akun = CustomerUser::create([
            'name' => 'Belum Terhubung', 'username' => 'kosong', 'password' => 'rahasia123',
            'role_id' => Role::portal()->where('slug', 'portal-penuh')->value('id'),
        ]);

        // Diloloskan pun ia hanya melihat portal kosong tanpa penjelasan.
        $this->post(route('portal.login'), [
            'username' => 'kosong',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest(CustomerUser::GUARD);
        $this->assertNull($akun->fresh()->last_login_at);
    }

    public function test_logout_portal_tidak_mengganggu_route_staf(): void
    {
        $this->actingAs($this->akun(), CustomerUser::GUARD);

        $this->post(route('portal.logout'))->assertRedirect(route('portal.login'));

        $this->assertGuest(CustomerUser::GUARD);
    }
}
