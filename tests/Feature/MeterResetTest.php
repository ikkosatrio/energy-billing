<?php

namespace Tests\Feature;

use App\Livewire\Monitoring\HistoryPage;
use App\Livewire\Monitoring\RealtimePage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\MeterReadingDaily;
use App\Models\PowerMeter;
use App\Models\TariffGroup;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\UsageCalculator;
use App\Services\Monitoring\ConsumptionCalculator;
use App\Services\Monitoring\DailyAggregationService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Meter yang di-reset di tengah periode.
 *
 * Skenario acuan: dipakai 300 kWh, meter di-reset ke 0, lalu dipakai 80 kWh
 * lagi. Pemakaian sebenarnya 380 kWh.
 *
 * Dua cara naif yang pernah dipakai dan sama-sama salah:
 *   max(0, akhir - awal)   ->   0     (pemakaian hilang, pelanggan tidak ditagih)
 *   MAX(stand) - MIN()     -> 9.300   (membengkak 24x, tanpa tanda apa pun)
 */
class MeterResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    private function meterWithReset(): PowerMeter
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-RESET', 'name' => 'Panel Reset', 'multiplier' => 1, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 00:00:00', 'stand_lwbp' => 9000, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 06:00:00', 'stand_lwbp' => 9150, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 12:00:00', 'stand_lwbp' => 9300, 'stand_wbp' => 0, 'source' => 'api'],
            // Reset
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 13:00:00', 'stand_lwbp' => 0, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 18:00:00', 'stand_lwbp' => 40, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 23:00:00', 'stand_lwbp' => 80, 'stand_wbp' => 0, 'source' => 'api'],
        ]);

        return $meter;
    }

    public function test_kalkulator_menjumlahkan_selisih_positif(): void
    {
        $meter = $this->meterWithReset();

        $usage = app(ConsumptionCalculator::class)->fromReadings(
            MeterReading::where('power_meter_id', $meter->id)->orderBy('read_at')->get()
        );

        $this->assertEquals(380, $usage['lwbp']);
        $this->assertSame(1, $usage['reset_count']);
    }

    public function test_agregat_harian_tidak_kehilangan_pemakaian_saat_reset(): void
    {
        $meter = $this->meterWithReset();

        app(DailyAggregationService::class)->aggregate($meter, Carbon::parse('2026-07-10'));

        $daily = MeterReadingDaily::first();

        $this->assertEquals(380, $daily->kwh_lwbp);
        $this->assertSame(1, $daily->reset_count);
        $this->assertTrue($daily->has_reset);
    }

    public function test_invoice_menagih_pemakaian_sebenarnya_dan_menandainya(): void
    {
        $meter = $this->meterWithReset();

        $group = TariffGroup::create(['code' => 'B-3/TR', 'name' => 'B-3']);
        $group->rates()->create(['rate_lwbp' => 1000, 'rate_wbp' => 1400, 'effective_from' => '2026-01-01']);

        Customer::create([
            'code' => 'C-RESET', 'name' => 'Pelanggan Reset',
            'power_meter_id' => $meter->id, 'tariff_group_id' => $group->id,
            'biaya_beban_mode' => 'flat', 'status' => 'active',
        ]);

        $generator = app(InvoiceGenerator::class);
        $generator->generate($generator->periodFor(Carbon::parse('2026-07-01')));

        $invoice = Invoice::firstOrFail();

        // Sebelum perbaikan, angka ini 0 — pelanggan tidak ditagih sama sekali.
        $this->assertEquals(380, $invoice->kwh_lwbp);
        $this->assertEquals(380 * 1000, $invoice->amount_lwbp);
        $this->assertStringContainsString('Stand meter mundur', $invoice->notes);
        // Tetap draft supaya diperiksa manusia sebelum ditagihkan.
        $this->assertSame('draft', $invoice->status);
    }

    public function test_chart_per_jam_tidak_membengkak_saat_reset(): void
    {
        $meter = $this->meterWithReset();

        $hourly = Livewire::test(HistoryPage::class)
            ->set('meterId', $meter->id)
            ->set('day', '2026-07-10')
            ->viewData('hourly');

        $total = collect($hourly)->sum(fn ($h) => $h['lwbp'] + $h['wbp']);

        // MAX-MIN akan menghasilkan 9.300 di sini.
        $this->assertEquals(380, $total);
    }

    public function test_kwh_hari_ini_di_realtime_tidak_membengkak_saat_reset(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-RT', 'name' => 'Panel RT', 'multiplier' => 1, 'status' => 'active',
        ]);

        $today = now()->startOfDay();
        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => $today->copy()->addHours(1)->toDateTimeString(), 'stand_lwbp' => 9000, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => $today->copy()->addHours(2)->toDateTimeString(), 'stand_lwbp' => 9300, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => $today->copy()->addHours(3)->toDateTimeString(), 'stand_lwbp' => 0, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => $today->copy()->addHours(4)->toDateTimeString(), 'stand_lwbp' => 80, 'stand_wbp' => 0, 'source' => 'api'],
        ]);

        $usage = Livewire::test(RealtimePage::class)->viewData('usage');

        $this->assertEquals(380, $usage[$meter->id]['today']['kwh']);
        // Reset hari ini tidak boleh ikut membengkakkan akumulasi di sebelahnya.
        $this->assertEquals(380, $usage[$meter->id]['week']['kwh']);
        $this->assertEquals(380, $usage[$meter->id]['month']['kwh']);
    }

    public function test_pembacaan_normal_hasilnya_sama_seperti_selisih_stand(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-OK', 'name' => 'Panel Normal', 'multiplier' => 1, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 00:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 500, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 12:00:00', 'stand_lwbp' => 1120, 'stand_wbp' => 530, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 23:00:00', 'stand_lwbp' => 1240, 'stand_wbp' => 560, 'source' => 'api'],
        ]);

        app(DailyAggregationService::class)->aggregate($meter, Carbon::parse('2026-07-10'));
        $daily = MeterReadingDaily::first();

        // Tanpa reset, hasilnya identik dengan selisih stand awal-akhir.
        $this->assertEquals(240, $daily->kwh_lwbp);
        $this->assertEquals(60, $daily->kwh_wbp);
        $this->assertSame(0, $daily->reset_count);
    }

    // ── Rollover register ────────────────────────────────────────────────

    /**
     * Register 6 digit penuh lalu berputar. Berbeda dari reset: sisa pemakaian
     * antara pembacaan terakhir dan titik putar tetap harus ditagih.
     */
    private function meterWithRollover(?float $standMax): PowerMeter
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-ROLL', 'name' => 'Panel Rollover', 'multiplier' => 1,
            'stand_max' => $standMax, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 10:00:00', 'stand_lwbp' => 999_900, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 11:00:00', 'stand_lwbp' => 999_980, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 12:00:00', 'stand_lwbp' => 30, 'stand_wbp' => 0, 'source' => 'api'],
        ]);

        return $meter;
    }

    public function test_rollover_menghitung_sisa_sampai_titik_putar(): void
    {
        $meter = $this->meterWithRollover(999_999.99);

        $usage = app(ConsumptionCalculator::class)->fromReadings(
            MeterReading::where('power_meter_id', $meter->id)->orderBy('read_at')->get(),
            $meter->effective_stand_max,
        );

        // 80 (naik biasa) + 19,99 (sisa sampai titik putar) + 30 (setelah putar)
        $this->assertEqualsWithDelta(129.99, $usage['lwbp'], 0.01);
        $this->assertSame(1, $usage['rollover_count']);
        $this->assertSame(0, $usage['reset_count']);
    }

    public function test_tanpa_stand_max_rollover_diperlakukan_sebagai_reset(): void
    {
        $meter = $this->meterWithRollover(null);

        $usage = app(ConsumptionCalculator::class)->fromReadings(
            MeterReading::where('power_meter_id', $meter->id)->orderBy('read_at')->get(),
            $meter->effective_stand_max,
        );

        // Sisa 19,99 kWh sampai titik putar tidak bisa diketahui tanpa stand_max.
        $this->assertEquals(110, $usage['lwbp']);
        $this->assertSame(1, $usage['reset_count']);
        $this->assertSame(0, $usage['rollover_count']);
    }

    /**
     * Stand 9.000 yang jatuh ke 0 pada meter berbatas 999.999 jelas bukan
     * rollover — itu penggantian meter, dan tidak boleh ditambahi sisa
     * 990.999 kWh yang tidak pernah dipakai.
     */
    public function test_reset_jauh_dari_batas_tidak_dianggap_rollover(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-JAUH', 'name' => 'Panel Jauh', 'multiplier' => 1,
            'stand_max' => 999_999.99, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 10:00:00', 'stand_lwbp' => 9_000, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 11:00:00', 'stand_lwbp' => 80, 'stand_wbp' => 0, 'source' => 'api'],
        ]);

        $usage = app(ConsumptionCalculator::class)->fromReadings(
            MeterReading::where('power_meter_id', $meter->id)->orderBy('read_at')->get(),
            $meter->effective_stand_max,
        );

        $this->assertEquals(80, $usage['lwbp']);
        $this->assertSame(1, $usage['reset_count']);
        $this->assertSame(0, $usage['rollover_count']);
    }

    public function test_stand_max_dikali_rasio_ct(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-CT', 'name' => 'Panel CT', 'multiplier' => 160,
            'stand_max' => 999_999.99, 'status' => 'active',
        ]);

        // Pembacaan disimpan sudah dikali CT, jadi titik putarnya ikut dikali.
        $this->assertEqualsWithDelta(159_999_998.4, $meter->effective_stand_max, 0.1);
    }

    public function test_agregat_harian_mencatat_rollover_sebagai_anomali(): void
    {
        $meter = $this->meterWithRollover(999_999.99);

        app(DailyAggregationService::class)->aggregate($meter, Carbon::parse('2026-07-10'));

        $daily = MeterReadingDaily::first();

        $this->assertEqualsWithDelta(129.99, $daily->kwh_lwbp, 0.01);
        $this->assertTrue($daily->has_reset);
    }

    // ── Reset berulang dalam satu periode ────────────────────────────────

    /**
     * Regresi: deteksi reset yang hanya membandingkan stand pertama dengan
     * stand terakhir gagal total di sini.
     *
     * Meter di-reset 100 kali; stand terakhir (20) kebetulan LEBIH TINGGI
     * daripada stand awal (0), sehingga pemeriksaan akhir-vs-awal membacanya
     * sebagai normal dan menagih 20 kWh dari 5.020 kWh yang sebenarnya
     * terpakai — kurang tagih 99,6% tanpa peringatan apa pun.
     */
    public function test_reset_berulang_yang_berakhir_di_stand_lebih_tinggi(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-100', 'name' => 'Panel Sering Reset', 'multiplier' => 1, 'status' => 'active',
        ]);

        $rows = [];
        $t = Carbon::parse('2026-07-01 00:00:00');
        $rows[] = ['power_meter_id' => $meter->id, 'read_at' => $t->copy()->toDateTimeString(), 'stand_lwbp' => 0, 'stand_wbp' => 0, 'source' => 'api'];

        // 100 siklus: naik 50 kWh lalu di-reset ke nol.
        for ($i = 0; $i < 100; $i++) {
            $t->addHours(3);
            $rows[] = ['power_meter_id' => $meter->id, 'read_at' => $t->copy()->toDateTimeString(), 'stand_lwbp' => 50, 'stand_wbp' => 0, 'source' => 'api'];
            $t->addHours(3);
            $rows[] = ['power_meter_id' => $meter->id, 'read_at' => $t->copy()->toDateTimeString(), 'stand_lwbp' => 0, 'stand_wbp' => 0, 'source' => 'api'];
        }

        $t->addHours(3);
        $rows[] = ['power_meter_id' => $meter->id, 'read_at' => $t->copy()->toDateTimeString(), 'stand_lwbp' => 20, 'stand_wbp' => 0, 'source' => 'api'];

        foreach (array_chunk($rows, 300) as $chunk) {
            MeterReading::insert($chunk);
        }

        // Agregasi harian berjalan lebih dulu, seperti scheduler di produksi.
        $aggregator = app(DailyAggregationService::class);
        for ($d = Carbon::parse('2026-07-01'); $d->lte(Carbon::parse('2026-07-31')); $d->addDay()) {
            $aggregator->aggregate($meter, $d->copy());
        }

        $usage = app(UsageCalculator::class)->forPeriod(
            $meter, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'),
        );

        // Stand terakhir memang lebih tinggi dari stand awal.
        $this->assertGreaterThan($usage['stand_lwbp_start'], $usage['stand_lwbp_end']);

        $this->assertTrue($usage['meter_reset'], 'Reset di tengah periode harus tetap terdeteksi.');
        $this->assertEquals(5020, $usage['kwh_lwbp']);
    }

    /**
     * Tanpa agregat harian, tidak ada cara murah memastikan tidak ada reset di
     * tengah periode — jalur teliti harus tetap ditempuh.
     */
    public function test_tanpa_agregat_harian_tetap_menempuh_jalur_teliti(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-NOAGG', 'name' => 'Tanpa Agregat', 'multiplier' => 1, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-01 00:00:00', 'stand_lwbp' => 0, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 00:00:00', 'stand_lwbp' => 500, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-11 00:00:00', 'stand_lwbp' => 0, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-31 00:00:00', 'stand_lwbp' => 300, 'stand_wbp' => 0, 'source' => 'api'],
        ]);

        // Sengaja TIDAK menjalankan agregasi harian.
        $usage = app(UsageCalculator::class)->forPeriod(
            $meter, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'),
        );

        $this->assertEquals(800, $usage['kwh_lwbp']);
        $this->assertTrue($usage['meter_reset']);
    }

    /**
     * Meter normal tidak boleh ikut ditandai reset hanya karena menempuh
     * jalur teliti saat agregat harian belum ada.
     */
    public function test_meter_normal_tanpa_agregat_tidak_ditandai_reset(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-NORM', 'name' => 'Normal', 'multiplier' => 1, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-01 00:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 500, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-31 00:00:00', 'stand_lwbp' => 1240, 'stand_wbp' => 560, 'source' => 'api'],
        ]);

        $usage = app(UsageCalculator::class)->forPeriod(
            $meter, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'),
        );

        $this->assertEquals(240, $usage['kwh_lwbp']);
        $this->assertEquals(60, $usage['kwh_wbp']);
        $this->assertFalse($usage['meter_reset']);
    }

    // ── Konsistensi invoice vs laporan ───────────────────────────────────

    /**
     * Regresi: agregat harian sempat hanya menghitung dari pembacaan pertama
     * sampai terakhir DI HARI ITU, sehingga pemakaian antara pembacaan
     * terakhir kemarin dan pembacaan pertama hari ini tidak masuk ke hari mana
     * pun. Hilangnya satu interval per hari — pada interval 30 menit itu 2%
     * sebulan, dan membuat Rekap Pemakaian tidak sama dengan invoice.
     *
     * @dataProvider intervalProvider
     */
    public function test_jumlah_agregat_harian_sama_dengan_invoice(int $intervalMinutes, float $kwhPerInterval): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-SUM-'.$intervalMinutes, 'name' => 'Panel Konsisten',
            'multiplier' => 1, 'status' => 'active',
        ]);

        // Rentang sengaja pendek (5 hari) agar test tetap ringan; yang diuji
        // adalah kesinambungan di pergantian hari, dan itu sudah terwakili
        // oleh beberapa batas tengah malam.
        //
        // Data dimulai sehari sebelumnya supaya ada pembacaan pembuka.
        $rows = [];
        $stand = 1000.0;
        $t = Carbon::parse('2026-06-30 00:00:00');
        $until = Carbon::parse('2026-07-05 23:59:00');

        while ($t->lte($until)) {
            $rows[] = [
                'power_meter_id' => $meter->id,
                'read_at' => $t->copy()->toDateTimeString(),
                'stand_lwbp' => round($stand, 2),
                'stand_wbp' => 0,
                'source' => 'api',
            ];
            $stand += $kwhPerInterval;
            $t->addMinutes($intervalMinutes);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            MeterReading::insert($chunk);
        }

        $aggregator = app(DailyAggregationService::class);
        for ($d = Carbon::parse('2026-07-01'); $d->lte(Carbon::parse('2026-07-05')); $d->addDay()) {
            $aggregator->aggregate($meter, $d->copy());
        }

        $reportTotal = (float) MeterReadingDaily::where('power_meter_id', $meter->id)
            ->between('2026-07-01', '2026-07-05')
            ->sum('kwh_lwbp');

        $invoice = app(UsageCalculator::class)->forPeriod(
            $meter, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-05'),
        );

        $this->assertEqualsWithDelta($invoice['kwh_lwbp'], $reportTotal, 0.01,
            "Rekap Pemakaian harus sama dengan yang ditagihkan invoice (interval {$intervalMinutes} menit).");
    }

    public static function intervalProvider(): array
    {
        return [
            'interval 30 menit' => [30, 30.0],
            'interval 5 menit' => [5, 5.0],
            'interval 1 menit' => [1, 1.0],
        ];
    }

    /**
     * reading_count harus tetap menghitung pembacaan hari itu saja, tidak ikut
     * bertambah oleh pembacaan pembuka dari hari sebelumnya.
     */
    public function test_reading_count_tidak_ikut_menghitung_pembacaan_pembuka(): void
    {
        $meter = PowerMeter::create([
            'code' => 'MTR-COUNT', 'name' => 'Panel Hitung', 'multiplier' => 1, 'status' => 'active',
        ]);

        MeterReading::insert([
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-09 23:30:00', 'stand_lwbp' => 900, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 00:00:00', 'stand_lwbp' => 930, 'stand_wbp' => 0, 'source' => 'api'],
            ['power_meter_id' => $meter->id, 'read_at' => '2026-07-10 12:00:00', 'stand_lwbp' => 1000, 'stand_wbp' => 0, 'source' => 'api'],
        ]);

        app(DailyAggregationService::class)->aggregate($meter, Carbon::parse('2026-07-10'));

        $daily = MeterReadingDaily::first();

        $this->assertSame(2, $daily->reading_count);
        // 30 kWh melewati tengah malam + 70 kWh siang hari.
        $this->assertEquals(100, $daily->kwh_lwbp);
    }
}
