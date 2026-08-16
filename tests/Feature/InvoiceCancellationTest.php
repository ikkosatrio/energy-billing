<?php

namespace Tests\Feature;

use App\Livewire\Billing\InvoicePage;
use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PowerMeter;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\InvoiceDocumentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pembatalan invoice dan pemulihannya.
 *
 * Pembatalan di sini bersifat *void*, bukan hapus: nomornya tetap terpakai,
 * barisnya tetap ada, dan dokumennya wajib menjelaskan dirinya sendiri.
 */
class InvoiceCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Pengguna dengan hak membatalkan tapi TANPA invoice.reopen — dipakai
     * untuk memastikan pemulihan benar-benar terkunci di permission sendiri.
     */
    private function actingAsCanceller(): User
    {
        $role = Role::create(['name' => 'Billing', 'slug' => 'billing-test']);
        $role->permissions()->sync(
            Permission::whereIn('slug', ['invoice.view', 'invoice.delete'])->pluck('id'),
        );

        $user = User::create([
            'name' => 'Staff', 'username' => 'staff', 'email' => 'staff@test.local',
            'password' => 'secret123', 'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function invoice(string $status = 'issued', string $no = 'INV-1'): Invoice
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-'.$no, 'name' => 'Panel '.$no, 'multiplier' => 1, 'status' => 'active',
        ]);

        $customer = Customer::create([
            'code' => 'C-'.$no, 'name' => 'PT Contoh', 'email' => 'pelanggan@test.local',
            'power_meter_id' => $meter->id, 'status' => 'active',
        ]);

        $period = BillingPeriod::firstOrCreate(
            ['code' => '2026-07'],
            ['period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01'],
        );

        return Invoice::create([
            'invoice_no' => $no, 'billing_period_id' => $period->id, 'customer_id' => $customer->id,
            'customer_name' => $customer->name, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'issue_date' => '2026-08-01', 'total_amount' => 500_000, 'status' => $status,
        ]);
    }

    public function test_pembatalan_mencatat_waktu_pelaku_dan_alasan(): void
    {
        $user = $this->actingAsSuperAdmin();
        $invoice = $this->invoice();

        Livewire::test(InvoicePage::class)
            ->call('cancel', $invoice->id, 'Salah stand meter');

        $invoice->refresh();

        $this->assertSame('cancelled', $invoice->status);
        $this->assertSame('Salah stand meter', $invoice->cancel_reason);
        $this->assertSame($user->id, $invoice->cancelled_by);
        $this->assertNotNull($invoice->cancelled_at);
    }

    public function test_alasan_boleh_dikosongkan(): void
    {
        $this->actingAsSuperAdmin();
        $invoice = $this->invoice();

        Livewire::test(InvoicePage::class)->call('cancel', $invoice->id, '   ');

        $invoice->refresh();

        $this->assertSame('cancelled', $invoice->status);
        // String kosong disimpan sebagai null, bukan spasi.
        $this->assertNull($invoice->cancel_reason);
    }

    public function test_invoice_lunas_tidak_bisa_dibatalkan(): void
    {
        $this->actingAsSuperAdmin();
        $invoice = $this->invoice('paid');

        Livewire::test(InvoicePage::class)->call('cancel', $invoice->id);

        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_invoice_dibayar_sebagian_tidak_bisa_dibatalkan(): void
    {
        $this->actingAsSuperAdmin();
        $invoice = $this->invoice('partial');

        Livewire::test(InvoicePage::class)->call('cancel', $invoice->id);

        $this->assertSame('partial', $invoice->refresh()->status);
    }

    public function test_draft_dan_terbit_sama_sama_bisa_dibatalkan(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['draft', 'issued', 'overdue'] as $index => $status) {
            $invoice = $this->invoice($status, 'INV-'.$index);

            Livewire::test(InvoicePage::class)->call('cancel', $invoice->id);

            $this->assertSame('cancelled', $invoice->refresh()->status, "status {$status} seharusnya bisa dibatalkan");
        }
    }

    public function test_invoice_batal_tidak_bisa_dikirim_ke_pelanggan(): void
    {
        $invoice = $this->invoice('cancelled');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah dibatalkan');

        app(InvoiceDocumentService::class)->email($invoice);
    }

    public function test_pdf_invoice_batal_memuat_keterangan_pembatalan(): void
    {
        $this->actingAsSuperAdmin();
        $invoice = $this->invoice();

        Livewire::test(InvoicePage::class)->call('cancel', $invoice->id, 'Pelanggan pindah golongan');

        $html = view('billing.invoices.pdf', [
            'invoice' => $invoice->refresh(),
            'lines' => [],
            'totals' => [],
        ])->render();

        $this->assertStringContainsString('INVOICE DIBATALKAN', $html);
        $this->assertStringContainsString('Pelanggan pindah golongan', $html);
    }

    public function test_pdf_invoice_normal_tanpa_keterangan_pembatalan(): void
    {
        $html = view('billing.invoices.pdf', [
            'invoice' => $this->invoice(),
            'lines' => [],
            'totals' => [],
        ])->render();

        $this->assertStringNotContainsString('INVOICE DIBATALKAN', $html);
    }

    public function test_reopen_mengembalikan_invoice_batal_menjadi_draft(): void
    {
        $this->actingAsSuperAdmin();
        $invoice = $this->invoice();

        Livewire::test(InvoicePage::class)
            ->call('cancel', $invoice->id, 'Keliru')
            ->call('reopen', $invoice->id);

        $invoice->refresh();

        $this->assertSame('draft', $invoice->status);
        // Jejak pembatalan dibersihkan supaya dokumennya kembali bersih.
        $this->assertNull($invoice->cancelled_at);
        $this->assertNull($invoice->cancel_reason);
        $this->assertNull($invoice->cancelled_by);
    }

    public function test_reopen_hanya_untuk_invoice_yang_dibatalkan(): void
    {
        $this->actingAsSuperAdmin();
        $invoice = $this->invoice();

        Livewire::test(InvoicePage::class)->call('reopen', $invoice->id);

        $this->assertSame('issued', $invoice->refresh()->status);
    }

    public function test_reopen_ditolak_tanpa_permission_khusus(): void
    {
        $this->actingAsCanceller();
        $invoice = $this->invoice();

        Livewire::test(InvoicePage::class)->call('cancel', $invoice->id);
        $this->assertSame('cancelled', $invoice->refresh()->status);

        // Hak membatalkan tidak otomatis memberi hak memulihkan.
        Livewire::test(InvoicePage::class)
            ->call('reopen', $invoice->id)
            ->assertForbidden();

        $this->assertSame('cancelled', $invoice->refresh()->status);
    }

    public function test_filter_periode_default_ke_periode_terbaru(): void
    {
        $this->actingAsSuperAdmin();

        // Dibuat tidak berurutan supaya yang terpilih benar-benar ditentukan
        // oleh period_start, bukan urutan penyisipan.
        $lama = BillingPeriod::create([
            'code' => '2026-05', 'period_start' => '2026-05-01',
            'period_end' => '2026-05-31', 'cut_off_date' => '2026-06-01',
        ]);
        $baru = BillingPeriod::create([
            'code' => '2026-07', 'period_start' => '2026-07-01',
            'period_end' => '2026-07-31', 'cut_off_date' => '2026-08-01',
        ]);
        BillingPeriod::create([
            'code' => '2026-06', 'period_start' => '2026-06-01',
            'period_end' => '2026-06-30', 'cut_off_date' => '2026-07-01',
        ]);

        Livewire::test(InvoicePage::class)
            ->assertSet('periodFilter', $baru->id)
            ->assertNotSet('periodFilter', $lama->id);
    }

    public function test_filter_periode_kosong_bila_belum_ada_periode_sama_sekali(): void
    {
        $this->actingAsSuperAdmin();

        // Membuka daftar invoice tidak boleh membuat periode baru.
        Livewire::test(InvoicePage::class)->assertSet('periodFilter', null);

        $this->assertSame(0, BillingPeriod::count());
    }

    public function test_permission_reopen_terdaftar_di_seeder(): void
    {
        $this->assertDatabaseHas('permissions', ['slug' => 'invoice.reopen']);

        // Hanya super-admin (yang lolos lewat bypass) — tidak ada role bawaan
        // lain yang diberi hak ini.
        $roles = Role::with('permissions')->where('slug', '!=', Role::SUPER_ADMIN)->get();

        foreach ($roles as $role) {
            $this->assertFalse(
                $role->permissions->contains('slug', 'invoice.reopen'),
                "role {$role->slug} seharusnya tidak punya invoice.reopen",
            );
        }
    }
}
