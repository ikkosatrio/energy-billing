<?php

namespace Tests\Feature;

use App\Livewire\Master\PowerMeterPage;
use App\Livewire\Monitoring\RealtimePage;
use App\Models\Customer;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\PowerMeterStatus;
use App\Models\Role;
use App\Models\TariffGroup;
use App\Models\TariffRate;
use App\Models\User;
use App\Services\Monitoring\UsageSummaryService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ringkasan pemakaian pada kartu Real-time Monitoring, beserta filter jenis
 * sambungan di halaman monitoring dan master.
 */
class RealtimeUsageTest extends TestCase
{
    use RefreshDatabase;

    /** Sabtu — minggu berjalannya (Sen 10 Agt) masih di dalam bulan yang sama. */
    private const NOW = '2026-08-15 14:00:00';

    private const RATE_LWBP = 1_000.0;

    private const RATE_WBP = 1_500.0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);

        Carbon::setTestNow(self::NOW);

        $this->actingAs(User::create([
            'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@test.local',
            'password' => 'secret123', 'role_id' => Role::where('slug', Role::SUPER_ADMIN)->value('id'),
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function meter(array $overrides = [], bool $withTariff = true): PowerMeter
    {
        $meter = PowerMeter::create(array_merge([
            'code' => 'MTR-01', 'name' => 'Panel Satu', 'multiplier' => 1, 'status' => 'active',
        ], $overrides));

        $group = TariffGroup::firstOrCreate(['code' => 'I-3/TR'], ['name' => 'I-3']);

        if ($withTariff) {
            TariffRate::firstOrCreate(
                ['tariff_group_id' => $group->id, 'effective_from' => '2026-01-01'],
                ['rate_lwbp' => self::RATE_LWBP, 'rate_wbp' => self::RATE_WBP, 'rate_beban_per_kva' => 0],
            );
        }

        Customer::create([
            'code' => 'C-'.$meter->id, 'name' => 'Pelanggan '.$meter->id,
            'power_meter_id' => $meter->id, 'tariff_group_id' => $group->id, 'status' => 'active',
        ]);

        return $meter;
    }

    /** @param  array<string, array{0:float, 1:float}>  $days  tanggal => [lwbp, wbp] */
    private function daily(PowerMeter $meter, array $days): void
    {
        foreach ($days as $date => [$lwbp, $wbp]) {
            MeterReadingDaily::create([
                'power_meter_id' => $meter->id, 'date' => $date,
                'kwh_lwbp' => $lwbp, 'kwh_wbp' => $wbp,
                'peak_kw' => 0, 'reading_count' => 1440,
            ]);
        }
    }

    /** @param  array<int, array{0:string, 1:float, 2:float}>  $rows */
    private function readings(PowerMeter $meter, array $rows): void
    {
        MeterReading::insert(array_map(fn ($r) => [
            'power_meter_id' => $meter->id, 'read_at' => $r[0],
            'stand_lwbp' => $r[1], 'stand_wbp' => $r[2], 'source' => 'api',
        ], $rows));
    }

    private function summaryFor(PowerMeter $meter): array
    {
        return app(UsageSummaryService::class)
            ->forMeters(PowerMeter::with('customer')->whereKey($meter->id)->get())[$meter->id];
    }

    // ── Penjumlahan rentang ──────────────────────────────────────────────

    public function test_hari_ini_diambil_dari_pembacaan_mentah(): void
    {
        $meter = $this->meter();

        // Agregat harian hari ini biasanya belum dibuat job agregasi.
        $this->readings($meter, [
            ['2026-08-15 00:05:00', 1_000, 400],
            ['2026-08-15 13:00:00', 1_040, 410],
        ]);

        $summary = $this->summaryFor($meter);

        $this->assertEquals(50, $summary['today']['kwh']);
        $this->assertEquals(40 * self::RATE_LWBP + 10 * self::RATE_WBP, $summary['today']['rp']);
    }

    public function test_minggu_berjalan_dihitung_sejak_senin(): void
    {
        $meter = $this->meter();

        $this->daily($meter, [
            '2026-08-08' => [500, 0],   // Sabtu, minggu sebelumnya — tidak ikut
            '2026-08-09' => [500, 0],   // Minggu, minggu sebelumnya — tidak ikut
            '2026-08-10' => [100, 0],   // Senin, awal minggu berjalan
            '2026-08-14' => [200, 50],
        ]);
        $this->readings($meter, [
            ['2026-08-15 00:05:00', 1_000, 400],
            ['2026-08-15 13:00:00', 1_030, 400],
        ]);

        $summary = $this->summaryFor($meter);

        $this->assertEquals(380, $summary['week']['kwh']);
        $this->assertSame('2026-08-10', $summary['week_start']->toDateString());
    }

    public function test_bulan_berjalan_dihitung_sejak_tanggal_satu(): void
    {
        $meter = $this->meter();

        $this->daily($meter, [
            '2026-07-31' => [900, 0],   // bulan lalu — tidak ikut
            '2026-08-01' => [100, 0],
            '2026-08-14' => [200, 50],
        ]);
        $this->readings($meter, [
            ['2026-08-15 00:05:00', 1_000, 400],
            ['2026-08-15 13:00:00', 1_030, 400],
        ]);

        $summary = $this->summaryFor($meter);

        $this->assertEquals(380, $summary['month']['kwh']);
        $this->assertEquals(
            330 * self::RATE_LWBP + 50 * self::RATE_WBP,
            $summary['month']['rp'],
        );
    }

    /**
     * Minggu berjalan bisa dimulai sebelum tanggal 1. Kalau agregat hanya
     * dimuat sejak awal bulan, sisa minggu di bulan sebelumnya hilang.
     */
    public function test_minggu_yang_melewati_pergantian_bulan_tetap_utuh(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00'); // Minggu; awal minggunya 27 Juli

        $meter = $this->meter();

        $this->daily($meter, [
            '2026-07-26' => [999, 0],   // Minggu, sebelum awal minggu berjalan
            '2026-07-27' => [100, 0],
            '2026-07-31' => [200, 0],
            '2026-08-01' => [300, 0],
        ]);

        $summary = $this->summaryFor($meter);

        $this->assertSame('2026-07-27', $summary['week_start']->toDateString());
        $this->assertEquals(600, $summary['week']['kwh']);
        // Bulan berjalan tetap hanya menghitung Agustus.
        $this->assertEquals(300, $summary['month']['kwh']);
    }

    /**
     * Regresi: bila hari ini sudah punya agregat harian DAN pembacaan mentah,
     * angka mentah menggantikan agregatnya — bukan ditambahkan.
     */
    public function test_hari_ini_tidak_terhitung_dua_kali(): void
    {
        $meter = $this->meter();

        $this->daily($meter, ['2026-08-15' => [30, 0]]);
        $this->readings($meter, [
            ['2026-08-15 00:05:00', 1_000, 400],
            ['2026-08-15 13:00:00', 1_030, 400],
        ]);

        $summary = $this->summaryFor($meter);

        $this->assertEquals(30, $summary['today']['kwh']);
        $this->assertEquals(30, $summary['month']['kwh']);
    }

    // ── Hari terboros ────────────────────────────────────────────────────

    public function test_hari_terboros_beserta_tanggalnya(): void
    {
        $meter = $this->meter();

        $this->daily($meter, [
            '2026-08-03' => [100, 0],
            '2026-08-07' => [400, 82],
            '2026-08-11' => [250, 0],
        ]);

        $summary = $this->summaryFor($meter);

        $this->assertEquals(482, $summary['peak']['kwh']);
        $this->assertSame('2026-08-07', $summary['peak']['date']->toDateString());
        $this->assertEquals(
            400 * self::RATE_LWBP + 82 * self::RATE_WBP,
            $summary['peak']['rp'],
        );
    }

    public function test_grafik_berisi_satu_batang_per_tanggal_sampai_hari_ini(): void
    {
        $meter = $this->meter();

        $this->daily($meter, ['2026-08-07' => [400, 0]]);

        $summary = $this->summaryFor($meter);

        // 1 s/d 15 Agustus, termasuk hari yang tidak berdata.
        $this->assertCount(15, $summary['days']);
        $this->assertSame('2026-08-01', $summary['days'][0]['date']->toDateString());
        $this->assertSame('2026-08-15', $summary['days'][14]['date']->toDateString());
        $this->assertTrue($summary['days'][14]['is_today']);
        $this->assertTrue($summary['days'][6]['is_peak']);
        $this->assertEquals(0, $summary['days'][0]['kwh']);
    }

    public function test_bulan_tanpa_pemakaian_tidak_punya_puncak(): void
    {
        $meter = $this->meter();

        $summary = $this->summaryFor($meter);

        $this->assertEquals(0, $summary['max_kwh']);
        $this->assertNull($summary['peak']);
        $this->assertFalse(collect($summary['days'])->contains('is_peak', true));
    }

    // ── Tarif ────────────────────────────────────────────────────────────

    public function test_tanpa_tarif_berlaku_rupiah_kosong_bukan_nol(): void
    {
        $meter = $this->meter(withTariff: false);

        $this->daily($meter, ['2026-08-07' => [400, 0]]);

        $summary = $this->summaryFor($meter);

        $this->assertFalse($summary['has_rate']);
        // Angka kWh-nya tetap benar; hanya rupiahnya yang belum bisa dihitung.
        $this->assertEquals(400, $summary['month']['kwh']);
        $this->assertNull($summary['month']['rp']);
        $this->assertNull($summary['peak']['rp']);
    }

    public function test_meter_tanpa_pelanggan_tetap_muncul_tanpa_rupiah(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-LEPAS', 'name' => 'Panel Lepas', 'multiplier' => 1, 'status' => 'active',
        ]);

        $this->daily($meter, ['2026-08-07' => [120, 0]]);

        $summary = $this->summaryFor($meter);

        $this->assertEquals(120, $summary['month']['kwh']);
        $this->assertNull($summary['month']['rp']);
    }

    // ── Filter jenis sambungan ───────────────────────────────────────────

    public function test_filter_phase_di_realtime(): void
    {
        $this->meter(['code' => 'MTR-3F', 'name' => 'Panel Tiga', 'phase' => '3']);
        $this->meter(['code' => 'MTR-1F', 'name' => 'Panel Satu Phase', 'phase' => '1']);

        Livewire::test(RealtimePage::class)
            ->assertSee('Panel Tiga')
            ->assertSee('Panel Satu Phase')
            ->set('phaseFilter', '1')
            ->assertSee('Panel Satu Phase')
            ->assertDontSee('Panel Tiga')
            ->set('phaseFilter', '3')
            ->assertSee('Panel Tiga')
            ->assertDontSee('Panel Satu Phase');
    }

    public function test_jumlah_per_phase_ditampilkan_di_filter_realtime(): void
    {
        $this->meter(['code' => 'MTR-3F', 'name' => 'Panel Tiga', 'phase' => '3']);
        $this->meter(['code' => 'MTR-1F', 'name' => 'Panel Satu Phase', 'phase' => '1']);
        // Meter nonaktif tidak ditampilkan, jadi tidak boleh ikut dihitung.
        PowerMeter::create(['code' => 'MTR-OFF', 'name' => 'Panel Mati', 'multiplier' => 1, 'status' => 'inactive', 'phase' => '1']);

        $counts = Livewire::test(RealtimePage::class)->viewData('phaseCounts');

        $this->assertSame(1, $counts['1']);
        $this->assertSame(1, $counts['3']);
        $this->assertSame(2, $counts['all']);
    }

    // ── Stand register di kartu "Sekarang" ───────────────────────────────

    public function test_stand_register_ditampilkan_bukan_daya_sesaat(): void
    {
        $meter = $this->meter();
        $this->readings($meter, [['2026-08-15 10:00:00', 1_270_280.5, 414_260.2]]);

        Livewire::test(RealtimePage::class)
            ->assertSee('Stand LWBP')
            ->assertSee('Stand WBP')
            ->assertSee('1.270.281')
            ->assertSee('414.260')
            ->assertDontSee('Power Factor');
    }

    /**
     * Payload status boleh tidak menyertakan stand — lihat DeviceStatusService.
     * Kartu tetap harus menampilkan stand dari pembacaan terakhir, bukan "—".
     */
    public function test_stand_jatuh_balik_ke_pembacaan_saat_status_tidak_menyertakannya(): void
    {
        $meter = $this->meter();
        $this->readings($meter, [['2026-08-15 10:00:00', 500.0, 200.0]]);

        PowerMeterStatus::create([
            'power_meter_id' => $meter->id,
            'active_power_kw' => 12.5,
            'read_at' => now(),
        ]);

        Livewire::test(RealtimePage::class)
            ->assertSee('500')
            ->assertSee('200');
    }

    public function test_stand_kosong_saat_belum_ada_data_sama_sekali(): void
    {
        $this->meter();

        Livewire::test(RealtimePage::class)->assertSeeInOrder(['Stand LWBP', '—']);
    }

    // ── Jeda penyegaran ──────────────────────────────────────────────────

    public function test_jeda_penyegaran_menghasilkan_polling(): void
    {
        $this->meter();

        Livewire::test(RealtimePage::class)
            ->assertSeeHtml('wire:poll.30s')
            ->set('refreshEvery', 5)
            ->assertSeeHtml('wire:poll.5s')
            ->set('refreshEvery', 600)
            ->assertSeeHtml('wire:poll.600s');
    }

    public function test_manual_mematikan_polling(): void
    {
        $this->meter();

        Livewire::test(RealtimePage::class)
            ->set('refreshEvery', 0)
            ->assertDontSeeHtml('wire:poll');
    }

    /**
     * Nilai di luar daftar hanya bisa datang dari payload yang dikarang.
     * Dikembalikan ke manual, bukan dipakai sebagai jeda polling.
     */
    public function test_jeda_di_luar_daftar_dikembalikan_ke_manual(): void
    {
        $this->meter();

        Livewire::test(RealtimePage::class)
            ->set('refreshEvery', 1)
            ->assertSet('refreshEvery', 0)
            ->assertDontSeeHtml('wire:poll');
    }

    public function test_pilihan_jeda_tersimpan_di_sesi(): void
    {
        $this->meter();

        Livewire::test(RealtimePage::class)->set('refreshEvery', 5);

        // Membuka halaman lagi tidak mengembalikannya ke nilai awal.
        Livewire::test(RealtimePage::class)->assertSet('refreshEvery', 5);
    }

    public function test_keterangan_interval_gateway_tidak_lagi_ditampilkan(): void
    {
        $this->meter();

        Livewire::test(RealtimePage::class)
            ->assertDontSee('Gateway mengirim')
            ->assertSee('perangkat ditampilkan');
    }

    public function test_filter_phase_di_master_power_meter(): void
    {
        $this->meter(['code' => 'MTR-3F', 'name' => 'Panel Tiga', 'phase' => '3']);
        $this->meter(['code' => 'MTR-1F', 'name' => 'Panel Satu Phase', 'phase' => '1']);

        Livewire::test(PowerMeterPage::class)
            ->set('phaseFilter', '1')
            ->assertSee('MTR-1F')
            ->assertDontSee('MTR-3F')
            ->set('phaseFilter', '')
            ->assertSee('MTR-1F')
            ->assertSee('MTR-3F');
    }
}
