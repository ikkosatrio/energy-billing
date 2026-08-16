<?php

namespace Database\Seeders;

use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\MeterReadingDaily;
use App\Models\MeterTariffSchedule;
use App\Models\PaymentBatch;
use App\Models\PowerMeter;
use App\Models\PowerMeterStatus;
use App\Models\TariffGroup;
use App\Models\User;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\ReceiptService;
use App\Services\Monitoring\DailyAggregationService;
use App\Services\SettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data contoh yang sengaja memuat SELURUH kondisi yang ditangani aplikasi —
 * bukan sekadar data yang "kelihatan bagus".
 *
 * Tujuannya supaya setiap cabang tampilan bisa dilihat tanpa perlu mengarang
 * data sendiri: sinyal dari kuat sampai tidak terdeteksi, meter 1 & 3 phase,
 * perangkat offline, pembacaan yang bolong dan stand yang mundur, pelanggan
 * yang tidak bisa ditagih, invoice di semua status, pembayaran cicilan, batch
 * massal dan impor beserta yang sudah ditarik kembali, sampai kuitansi yang
 * sudah dan belum terkirim.
 *
 * Daftar lengkap skenarionya ada di const SCENARIOS di bawah.
 *
 * TIDAK dipanggil DatabaseSeeder — jalankan sendiri bila diperlukan:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /** Interval pembacaan mentah yang dibuat (menit). */
    private const READING_INTERVAL = 30;

    /** Berapa bulan agregat harian dibuat ke belakang (untuk chart & rekap). */
    private const AGGREGATE_MONTHS = 6;

    /**
     * Satu baris per pelanggan/meter, masing-masing mewakili satu kondisi.
     *
     * Kolom:
     *   kode, nama, golongan, kVA, biaya beban flat (0 = mode per_kva),
     *   tgl tagih, kW rata-rata (0 = tidak ada pembacaan sama sekali),
     *   phase, dBm (null = perangkat belum pernah lapor), skenario
     *
     * Skenario dipakai createMeters() dan applyScenarios() untuk menentukan
     * status meter, kondisi pembacaan, dan nasib invoicenya.
     */
    private const SCENARIOS = [
        // [kode, nama, golongan, kVA, beban, tglTagih, avgKw, phase, dBm, skenario]
        ['C-001', 'PT Sinar Abadi Logistik', 'I-3/TR', 630, 9_450_000, 1, 410, '3', -48, 'lunas_massal'],
        ['C-002', 'CV Mitra Cold Storage', 'I-3/TR', 555, 8_325_000, 1, 295, '3', -63, 'cicilan'],
        ['C-003', 'PT Karya Pangan Sejahtera', 'B-3/TR', 345, 5_175_000, 5, 155, '3', -72, 'jatuh_tempo'],
        ['C-004', 'PT Anugerah Tekstil', 'B-3/TR', 240, 3_600_000, 5, 92, '3', -81, 'dibatalkan'],
        ['C-005', 'PT Jaya Distribusi', 'I-4/TM', 865, 0, 10, 220, '1', -93, 'draft'],
        ['C-006', 'PT Bahari Fresh', 'B-3/TR', 240, 3_600_000, 10, 0, '1', null, 'tanpa_pembacaan'],
        ['C-007', 'UD Terang Benderang', 'I-3/TR', 197, 2_955_000, 15, 128, '3', -66, 'tanpa_email'],
        ['C-008', 'PT Nusantara Plastik', 'B-3/TR', 345, 5_175_000, 15, 176, '1', -58, 'anomali_data'],
        ['C-009', 'CV Rezeki Mandiri', 'B-3/TR', 105, 1_575_000, 20, 64, '3', -70, 'pelanggan_nonaktif'],
    ];

    public function run(): void
    {
        if (Customer::exists()) {
            $this->command?->warn('Data pelanggan sudah ada — DemoDataSeeder dilewati.');

            return;
        }

        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            TariffGroupSeeder::class,
            UserSeeder::class,
        ]);

        // Interval push disamakan dengan interval data yang dibuat di bawah.
        // Tanpa ini, halaman Data Meter Mentah menandai SETIAP baris sebagai
        // "jeda data" — bukan karena datanya bermasalah, melainkan karena
        // settingnya bercerita lain.
        app(SettingService::class)->put('iot_push_interval_seconds', self::READING_INTERVAL * 60);

        $meters = $this->createMeters();
        $this->createCustomers($meters);
        $this->createOrphanMeter();
        $this->createTariffHistory();

        $this->createReadings($meters);
        $this->aggregateRecent();
        $this->backfillOlderAggregates($meters);

        $this->createInvoices();
        $this->applyScenarios();
        $this->createAgingInvoices();
        $this->closeOldestPeriod();

        $this->report();
    }

    // ── Meter & perangkat ────────────────────────────────────────────────

    /**
     * @return array<string, array{meter:PowerMeter, avgKw:float, scenario:string}>
     */
    private function createMeters(): array
    {
        $devices = [
            'Schneider|AW9L', 'Schneider|AW9L', 'Eastron|SM630', 'Eastron|SM630',
            'CHINT|DTS353', 'CHINT|DTS353', 'Schneider|AW9L', 'Eastron|SM630', 'CHINT|DTS353',
        ];

        $meters = [];

        foreach (self::SCENARIOS as $index => $row) {
            [$code, , , $kva, , , $avgKw, $phase, $dbm, $scenario] = $row;
            [$brand, $model] = explode('|', $devices[$index]);

            // Meter nonaktif dan maintenance sengaja ada supaya filter status
            // dan badge koneksi di Monitoring punya isi.
            $status = match ($scenario) {
                'tanpa_pembacaan' => 'maintenance',
                'pelanggan_nonaktif' => 'inactive',
                default => 'active',
            };

            // last_seen_at menentukan online/offline (ambang 5 menit).
            // 'tanpa_email' dibuat offline supaya badge merah ikut terlihat
            // pada meter yang selebihnya sehat.
            $lastSeen = match (true) {
                $status !== 'active' => null,
                $scenario === 'tanpa_email' => now()->subHours(3),
                default => now(),
            };

            $meter = PowerMeter::create([
                'code' => $this->meterCode($index, $brand),
                'name' => 'Panel '.Str::after($code, 'C-'),
                'serial_no' => strtoupper(Str::random(10)),
                'brand' => $brand,
                'model' => $model,
                'location' => $this->location($index),
                'ct_ratio' => $kva > 500 ? '800/5' : '400/5',
                'multiplier' => 1,
                // Register 6 digit: dipasang hanya pada meter beranomali supaya
                // pembedaan reset vs rollover punya contoh nyata.
                'stand_max' => $scenario === 'anomali_data' ? 999_999.99 : null,
                'phase' => $phase,
                'status' => $status,
                'installed_at' => now()->subYear()->toDateString(),
                'last_seen_at' => $lastSeen,
            ]);

            $this->createSchedule($meter, $scenario);
            $this->createDeviceStatus($meter, $dbm, (float) $avgKw, $index);

            $meters[$code] = [
                'meter' => $meter,
                'avgKw' => (float) $avgKw,
                'scenario' => $scenario,
            ];
        }

        return $meters;
    }

    private function meterCode(int $index, string $brand): string
    {
        $prefix = match ($brand) {
            'Schneider' => 'AW9L-IRC',
            'Eastron' => 'SM630-B',
            default => 'DTS353-C',
        };

        return $prefix.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    }

    private function location(int $index): string
    {
        $spots = [
            'Main Distribution Panel', 'Panel Gudang B', 'Panel Gudang B1', 'Panel Gudang B2',
            'Panel Gudang C1', 'Panel Gudang C2', 'Panel Workshop', 'Panel Gudang D1', 'Panel Kantor',
        ];

        return $spots[$index % count($spots)];
    }

    /**
     * Kondisi terakhir perangkat — normalnya datang dari POST /api/v1/status.
     *
     * dBm sengaja menyebar melewati SELURUH ambang mutu sinyal (kuat, baik,
     * cukup, lemah, sangat lemah), ditambah dua kasus tanpa data: perangkat
     * yang belum pernah lapor sama sekali, dan yang lapor tanpa menyertakan
     * kekuatan sinyal.
     */
    private function createDeviceStatus(PowerMeter $meter, ?int $dbm, float $avgKw, int $index): void
    {
        // Meter yang belum pernah lapor: tidak dibuatkan baris status sama
        // sekali, supaya tampilan "Belum ada data" ikut teruji.
        if ($dbm === null) {
            return;
        }

        $tigaPhase = !$meter->isSinglePhase();

        PowerMeterStatus::create([
            'power_meter_id' => $meter->id,
            // Satu perangkat melapor tanpa signal_dbm — payloadnya sah, tapi
            // kolom sinyal harus tetap menampilkan sesuatu yang masuk akal.
            'signal_dbm' => $index === 8 ? null : $dbm,
            'ip_address' => '192.168.10.'.(21 + $index),
            'mac_address' => sprintf('A4:CF:12:9B:00:%02X', $index + 1),
            'firmware_version' => ['1.4.2', '1.3.9', '2.0.1'][$index % 3],
            'active_power_kw' => $avgKw ?: null,
            'voltage_r' => 229.6,
            'voltage_s' => $tigaPhase ? 228.1 : null,
            'voltage_t' => $tigaPhase ? 230.4 : null,
            'current_r' => $avgKw ? round($avgKw * 1.6, 1) : null,
            'current_s' => $tigaPhase && $avgKw ? round($avgKw * 1.55, 1) : null,
            'current_t' => $tigaPhase && $avgKw ? round($avgKw * 1.62, 1) : null,
            'power_factor' => 0.92,
            'frequency' => 50,
            'read_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Jadwal WBP/LWBP per meter.
     *
     * Satu meter sengaja diberi jadwal berbeda (WBP 18:00–23:00) supaya fitur
     * "Duplikat dari meter lain" punya sumber yang benar-benar berbeda untuk
     * dicoba — kalau semua jadwalnya sama, hasil duplikatnya tidak kelihatan.
     */
    private function createSchedule(PowerMeter $meter, string $scenario): void
    {
        $periods = $scenario === 'anomali_data'
            ? [
                ['sequence' => 1, 'start_time' => '00:00', 'end_time' => '18:00', 'tariff_type' => 'LWBP'],
                ['sequence' => 2, 'start_time' => '18:00', 'end_time' => '23:00', 'tariff_type' => 'WBP'],
                ['sequence' => 3, 'start_time' => '23:00', 'end_time' => '00:00', 'tariff_type' => 'LWBP'],
            ]
            : [
                ['sequence' => 1, 'start_time' => '00:00', 'end_time' => '17:00', 'tariff_type' => 'LWBP'],
                ['sequence' => 2, 'start_time' => '17:00', 'end_time' => '22:00', 'tariff_type' => 'WBP'],
                ['sequence' => 3, 'start_time' => '22:00', 'end_time' => '00:00', 'tariff_type' => 'LWBP'],
            ];

        foreach ($periods as $period) {
            MeterTariffSchedule::create($period + ['power_meter_id' => $meter->id]);
        }
    }

    /**
     * Meter yang belum dipasangkan ke pelanggan mana pun — kolom "Pelanggan"
     * di Master Data dan Monitoring harus menanganinya.
     */
    private function createOrphanMeter(): void
    {
        $meter = PowerMeter::create([
            'code' => 'AW9L-IRC99',
            'name' => 'Panel Cadangan',
            'serial_no' => strtoupper(Str::random(10)),
            'brand' => 'Schneider',
            'model' => 'AW9L',
            'location' => 'Gudang Sparepart',
            'ct_ratio' => '400/5',
            'multiplier' => 1,
            'phase' => '3',
            'status' => 'active',
            'installed_at' => now()->subMonths(2)->toDateString(),
            'last_seen_at' => null,
        ]);

        $this->createSchedule($meter, 'default');
    }

    // ── Pelanggan ────────────────────────────────────────────────────────

    /**
     * @param  array<string, array{meter:PowerMeter, avgKw:float, scenario:string}>  $meters
     */
    private function createCustomers(array $meters): void
    {
        foreach (self::SCENARIOS as $row) {
            [$code, $name, $groupCode, $kva, $beban, $billingDay, , , , $scenario] = $row;

            Customer::create([
                'code' => $code,
                'name' => $name,
                'address' => $this->address($code),
                'pic_name' => $this->pic($code),
                'phone' => $this->phone($code),
                // Satu pelanggan sengaja tanpa email: pengiriman invoice dan
                // kuitansi harus menolaknya dengan pesan yang jelas, bukan gagal
                // diam-diam.
                'email' => $scenario === 'tanpa_email' ? null : Str::slug(Str::before($name, ' ')).'@example.com',
                'power_meter_id' => $meters[$code]['meter']->id,
                'tariff_group_id' => TariffGroup::where('code', $groupCode)->value('id'),
                'daya_kva' => $kva,
                'biaya_beban_mode' => $beban > 0 ? 'flat' : 'per_kva',
                'biaya_beban' => $beban,
                'billing_day' => $billingDay,
                'contract_start' => now()->subYear()->toDateString(),
                'status' => $scenario === 'pelanggan_nonaktif' ? 'inactive' : 'active',
            ]);
        }

        // Dua pelanggan yang TIDAK bisa ditagih, masing-masing karena alasan
        // berbeda — keduanya harus tetap muncul di Master Data tapi dilewati
        // saat generate invoice (scope Customer::billable()).
        Customer::create([
            'code' => 'C-010',
            'name' => 'PT Calon Pelanggan',
            'address' => 'Kawasan Industri Jababeka Blok D-2, Cikarang',
            'pic_name' => 'Yusuf Maulana',
            'phone' => '0812-7788-1122',
            'email' => 'calon@example.com',
            'power_meter_id' => null,
            'tariff_group_id' => TariffGroup::where('code', 'I-3/TR')->value('id'),
            'daya_kva' => 240,
            'biaya_beban_mode' => 'flat',
            'biaya_beban' => 3_600_000,
            'billing_day' => 1,
            'contract_start' => now()->toDateString(),
            'status' => 'active',
        ]);

        Customer::create([
            'code' => 'C-011',
            'name' => 'CV Belum Bergolongan',
            'address' => 'Jl. Industri Raya 20, Cikarang',
            'pic_name' => 'Lestari Ningsih',
            'phone' => '0813-2211-9090',
            'email' => 'belumgolongan@example.com',
            'power_meter_id' => null,
            'tariff_group_id' => null,
            'daya_kva' => 105,
            'biaya_beban_mode' => 'flat',
            'biaya_beban' => 1_575_000,
            'billing_day' => 1,
            'contract_start' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    private function address(string $code): string
    {
        $streets = [
            'Kawasan Industri Jababeka Blok C-12, Cikarang',
            'Kawasan Industri Jababeka Blok C-14, Cikarang',
            'Gudang B1, Jl. Industri Raya 8, Cikarang',
            'Gudang B2, Jl. Industri Raya 8, Cikarang',
            'Gudang C1, Jl. Industri Raya 12, Cikarang',
            'Gudang C2, Jl. Industri Raya 12, Cikarang',
            'Jl. Raya Bekasi KM 27, Bekasi',
            'Gudang D1, Jl. Industri Raya 15, Cikarang',
            'Ruko Sentra Niaga Blok A-8, Cikarang',
        ];

        return $streets[((int) Str::after($code, 'C-') - 1) % count($streets)];
    }

    /** Nomor tetap per kode pelanggan — menjalankan seeder ulang tidak mengubahnya. */
    private function phone(string $code): string
    {
        $numbers = [
            '0812-1122-3344', '0813-9087-1120', '0821-4455-9911', '0817-2233-8899', '0878-6543-1200',
            '0811-3344-5566', '0856-7788-2200', '0819-4433-1177', '0852-6677-8811',
        ];

        return $numbers[((int) Str::after($code, 'C-') - 1) % count($numbers)];
    }

    private function pic(string $code): string
    {
        $names = [
            'Budi Santoso', 'Rina Wijaya', 'Hendra Pratama', 'Sari Melati', 'Agus Kurnia',
            'Dewi Anggraini', 'Fajar Nugroho', 'Maya Puspita', 'Rizal Hakim',
        ];

        return $names[((int) Str::after($code, 'C-') - 1) % count($names)];
    }

    /**
     * Riwayat tarif: satu golongan diberi tarif kedua yang berlaku sejak awal
     * bulan lalu, sehingga halaman Golongan & Tarif punya contoh tarif lama
     * yang sudah ditutup — bukan cuma satu baris tunggal.
     */
    private function createTariffHistory(): void
    {
        $group = TariffGroup::where('code', 'I-3/TR')->first();
        $lama = $group?->rates()->orderBy('effective_from')->first();

        if (!$group || !$lama) {
            return;
        }

        $mulai = now()->subMonth()->startOfMonth();

        $lama->update(['effective_to' => $mulai->copy()->subDay()->toDateString()]);

        $group->rates()->create([
            'rate_lwbp' => 1_167.32,
            'rate_wbp' => 1_634.25,
            'rate_beban_per_kva' => 42_000,
            'effective_from' => $mulai->toDateString(),
            'effective_to' => null,
            'notes' => 'Penyesuaian tarif — contoh riwayat perubahan.',
        ]);
    }

    // ── Pembacaan mentah ─────────────────────────────────────────────────

    /**
     * Pembacaan dua bulan terakhir dengan stand yang terus menaik.
     *
     * Meter beranomali mendapat dua gangguan yang sengaja dibuat:
     *   - jeda data: gateway "mati" beberapa jam
     *   - stand mundur: register diputar balik ke nol (rollover)
     * Keduanya adalah kondisi yang dideteksi ReportService::flagAnomalies()
     * dan ditandai merah di halaman Data Meter Mentah.
     *
     * @param  array<string, array{meter:PowerMeter, avgKw:float, scenario:string}>  $meters
     */
    private function createReadings(array $meters): void
    {
        $start = now()->subMonth()->startOfMonth();
        $end = now();

        foreach ($meters as $entry) {
            $meter = $entry['meter'];
            $avgKw = $entry['avgKw'];

            // Meter tanpa pembacaan sama sekali — invoicenya nanti bernilai nol
            // dan diberi catatan oleh InvoiceGenerator.
            if ($avgKw <= 0) {
                continue;
            }

            $anomali = $entry['scenario'] === 'anomali_data';
            $bolongMulai = now()->subDays(9)->startOfDay()->addHours(4);
            $bolongSelesai = $bolongMulai->copy()->addHours(7);
            $rolloverPada = now()->subDays(4)->startOfDay()->addHours(10);

            $standLwbp = $anomali ? 995_400.0 : 1_200_000.0;
            $standWbp = $anomali ? 380_500.0 : 400_000.0;
            $sudahRollover = false;

            $rows = [];
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                // Jeda data: baris pada rentang ini tidak dibuat sama sekali.
                if ($anomali && $cursor->betweenIncluded($bolongMulai, $bolongSelesai)) {
                    $cursor->addMinutes(self::READING_INTERVAL);

                    continue;
                }

                $hour = (int) $cursor->format('H');
                $isWbp = $hour >= 17 && $hour < 22;
                $factor = match (true) {
                    $hour < 6 => 0.55,
                    $hour < 17 => 1.0,
                    $hour < 22 => 0.85,
                    default => 0.65,
                };

                $kw = $avgKw * $factor * (0.9 + (($cursor->timestamp % 17) / 85));
                $kwh = $kw * (self::READING_INTERVAL / 60);

                $isWbp ? $standWbp += $kwh : $standLwbp += $kwh;

                // Rollover: register 6 digit penuh lalu berputar kembali.
                if ($anomali && !$sudahRollover && $cursor->gte($rolloverPada)) {
                    $standLwbp = max(0, $standLwbp - 999_999.99);
                    $sudahRollover = true;
                }

                $tigaPhase = !$meter->isSinglePhase();

                $rows[] = [
                    'power_meter_id' => $meter->id,
                    'read_at' => $cursor->toDateTimeString(),
                    'stand_lwbp' => round($standLwbp, 2),
                    'stand_wbp' => round($standWbp, 2),
                    'active_power_kw' => round($kw, 2),
                    'voltage_r' => 380 + ($cursor->timestamp % 5),
                    // Meter 1 phase tidak punya jalur S dan T — dibiarkan null
                    // supaya penyembunyian kolomnya ikut terlihat pada data ini.
                    'voltage_s' => $tigaPhase ? 379 + ($cursor->timestamp % 4) : null,
                    'voltage_t' => $tigaPhase ? 381 - ($cursor->timestamp % 3) : null,
                    'current_r' => round($kw * 1.5, 2),
                    'current_s' => $tigaPhase ? round($kw * 1.48, 2) : null,
                    'current_t' => $tigaPhase ? round($kw * 1.52, 2) : null,
                    'power_factor' => 0.92 + (($cursor->timestamp % 6) / 100),
                    'frequency' => 50,
                    // Sebagian kecil dicatat manual supaya badge "Manual" pada
                    // kolom Sumber tidak pernah kosong.
                    'source' => $cursor->day === 3 && $hour === 8 ? 'manual' : 'api',
                    'created_at' => $cursor->toDateTimeString(),
                ];

                $cursor->addMinutes(self::READING_INTERVAL);
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('meter_readings')->insert($chunk);
            }
        }
    }

    private function aggregateRecent(): void
    {
        $service = app(DailyAggregationService::class);
        $cursor = now()->subMonth()->startOfMonth();

        while ($cursor->lte(now())) {
            $service->aggregateAll($cursor->copy());
            $cursor->addDay();
        }
    }

    /**
     * Agregat harian untuk bulan-bulan sebelumnya, TANPA pembacaan mentahnya.
     *
     * Ini bukan jalan pintas yang malas: begitulah kondisi data yang sebenarnya
     * setelah masa retensi lewat — pembacaan mentah dibuang, agregat harian
     * tetap disimpan. Chart 12 bulan dan Rekap Pemakaian jadi punya riwayat
     * panjang tanpa menggelembungkan tabel meter_readings.
     *
     * @param  array<string, array{meter:PowerMeter, avgKw:float, scenario:string}>  $meters
     */
    private function backfillOlderAggregates(array $meters): void
    {
        $mulai = now()->subMonths(self::AGGREGATE_MONTHS)->startOfMonth();
        $selesai = now()->subMonth()->startOfMonth()->subDay();

        $rows = [];

        foreach ($meters as $entry) {
            $avgKw = $entry['avgKw'];

            if ($avgKw <= 0) {
                continue;
            }

            $meterId = $entry['meter']->id;
            $standLwbp = 900_000.0;
            $standWbp = 300_000.0;
            $cursor = $mulai->copy();

            while ($cursor->lte($selesai)) {
                // Akhir pekan lebih sepi — memberi bentuk pada chart harian.
                $musim = $cursor->isWeekend() ? 0.45 : 1.0;
                $lwbp = round($avgKw * 19 * $musim * (0.9 + ($cursor->dayOfYear % 11) / 55), 2);
                $wbp = round($avgKw * 5 * $musim * (0.9 + ($cursor->dayOfYear % 7) / 35), 2);

                $awalLwbp = $standLwbp;
                $awalWbp = $standWbp;
                $standLwbp += $lwbp;
                $standWbp += $wbp;

                $rows[] = [
                    'power_meter_id' => $meterId,
                    'date' => $cursor->toDateString(),
                    'stand_lwbp_start' => round($awalLwbp, 2),
                    'stand_lwbp_end' => round($standLwbp, 2),
                    'stand_wbp_start' => round($awalWbp, 2),
                    'stand_wbp_end' => round($standWbp, 2),
                    'kwh_lwbp' => $lwbp,
                    'kwh_wbp' => $wbp,
                    'peak_kw' => round($avgKw * 1.25, 2),
                    'peak_at' => $cursor->copy()->setTime(14, 0)->toDateTimeString(),
                    'reading_count' => 48,
                    'reset_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $cursor->addDay();
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            MeterReadingDaily::insert($chunk);
        }
    }

    // ── Invoice & pembayaran ─────────────────────────────────────────────

    private function createInvoices(): void
    {
        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse(now()->subMonth()->format('Y-m').'-01'));

        $generator->generate($period);
    }

    /**
     * Mendorong tiap invoice bulan lalu ke status yang skenarionya minta.
     *
     * Dilakukan setelah generate, bukan dengan membuat invoice sendiri, supaya
     * angkanya tetap hasil perhitungan sungguhan dari pembacaan meter.
     */
    private function applyScenarios(): void
    {
        $admin = User::orderBy('id')->first();
        $receipts = app(ReceiptService::class);

        foreach (self::SCENARIOS as $row) {
            [$code, , , , , , , , , $scenario] = $row;

            $invoice = Invoice::whereHas('customer', fn ($q) => $q->where('code', $code))->first();

            if (!$invoice) {
                continue;
            }

            match ($scenario) {
                'lunas_massal' => $this->settleViaBulkBatch($invoice, $admin, $receipts),
                'cicilan' => $this->settlePartially($invoice, $receipts),
                'jatuh_tempo' => $this->makeOverdue($invoice),
                'dibatalkan' => $this->cancel($invoice, $admin),
                'anomali_data' => $this->settleViaImportBatch($invoice, $admin),
                'tanpa_email' => $this->issueOnly($invoice),
                // 'draft' dan 'tanpa_pembacaan' dibiarkan apa adanya — keduanya
                // memang berhenti sebagai draft, yang kedua beserta catatannya.
                default => null,
            };
        }
    }

    private function issueOnly(Invoice $invoice): void
    {
        $invoice->forceFill([
            'status' => 'issued',
            'due_date' => now()->addDays(9)->toDateString(),
        ])->save();
    }

    /** Lunas penuh lewat "Tandai Lunas" massal, kuitansinya sudah terkirim. */
    private function settleViaBulkBatch(Invoice $invoice, ?User $admin, ReceiptService $receipts): void
    {
        $this->issueOnly($invoice);

        $batch = PaymentBatch::create([
            'type' => 'bulk',
            'payment_count' => 1,
            'total_amount' => $invoice->outstanding,
            'notes' => 'Rekonsiliasi mutasi bank — contoh pelunasan massal.',
            'created_by' => $admin?->id,
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->subDays(6)->toDateString(),
            'amount' => $invoice->outstanding,
            'method' => 'transfer',
            'reference_no' => 'TRF'.now()->format('ymd').'001',
            'recorded_by' => $admin?->id,
            'payment_batch_id' => $batch->id,
        ]);

        $receipts->issue($payment);
        $payment->forceFill(['receipt_sent_at' => now()->subDays(3)])->save();
    }

    /** Dua cicilan; kuitansi cicilan pertama terkirim, kedua belum. */
    private function settlePartially(Invoice $invoice, ReceiptService $receipts): void
    {
        $this->issueOnly($invoice);

        $total = (float) $invoice->total_amount;

        $pertama = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->subDays(12)->toDateString(),
            'amount' => round($total * 0.4, 2),
            'method' => 'transfer',
            'reference_no' => 'TRF'.now()->format('ymd').'002',
            'notes' => 'Cicilan pertama.',
        ]);

        $receipts->issue($pertama);
        $pertama->forceFill(['receipt_sent_at' => now()->subDays(11)])->save();

        // Cicilan kedua belum menutup tagihan — invoice tetap berstatus partial.
        $kedua = InvoicePayment::create([
            'invoice_id' => $invoice->refresh()->id,
            'payment_date' => now()->subDays(2)->toDateString(),
            'amount' => round($total * 0.25, 2),
            'method' => 'cash',
            'notes' => 'Cicilan kedua.',
        ]);

        // Kuitansinya sudah terbit tapi belum dikirim — persis kondisi selama
        // masa tunggu kirim otomatis.
        $receipts->issue($kedua);
    }

    /** Lewat jatuh tempo 18 hari — masuk bucket aging 1–30 hari. */
    private function makeOverdue(Invoice $invoice): void
    {
        $invoice->forceFill([
            'status' => 'overdue',
            'due_date' => now()->subDays(18)->toDateString(),
        ])->save();
    }

    private function cancel(Invoice $invoice, ?User $admin): void
    {
        $invoice->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(5),
            'cancel_reason' => 'Stand meter keliru saat generate — diterbitkan ulang periode berikutnya.',
            'cancelled_by' => $admin?->id,
        ])->save();
    }

    /**
     * Lunas lewat impor berkas. Batch impor kedua sengaja dibuat lalu ditarik
     * kembali, supaya riwayat "Operasi Massal Terakhir" punya contoh batch yang
     * sudah dibatalkan.
     */
    private function settleViaImportBatch(Invoice $invoice, ?User $admin): void
    {
        $this->issueOnly($invoice);

        $tanggal = now()->subDays(7)->toDateString();
        $referensi = 'TRF'.now()->format('ymd').'003';
        $jumlah = (float) $invoice->outstanding;

        $batch = PaymentBatch::create([
            'type' => 'import',
            'source' => 'mutasi-bca-'.$tanggal.'.xlsx',
            'payment_count' => 1,
            'total_amount' => $jumlah,
            'created_by' => $admin?->id,
        ]);

        // Kuitansinya sengaja TIDAK diterbitkan: kolom Kuitansi harus punya
        // contoh yang masih menawarkan "Terbitkan", bukan nomor.
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => $tanggal,
            'amount' => $jumlah,
            'method' => 'transfer',
            'reference_no' => $referensi,
            'recorded_by' => $admin?->id,
            'payment_batch_id' => $batch->id,
            'import_hash' => InvoicePayment::importHash($invoice->id, $tanggal, $jumlah, $referensi),
        ]);

        // Batch yang sudah ditarik kembali — pembayarannya memang sudah tidak
        // ada, barisnya tinggal sebagai jejak bahwa operasinya pernah terjadi.
        PaymentBatch::create([
            'type' => 'import',
            'source' => 'mutasi-bca-salah-unggah.xlsx',
            'payment_count' => 0,
            'total_amount' => 0,
            'created_by' => $admin?->id,
        ])->forceFill([
            'reverted_at' => now()->subDays(6),
            'reverted_by' => $admin?->id,
        ])->save();
    }

    /**
     * Invoice periode-periode sebelumnya yang belum dibayar, dengan jatuh tempo
     * yang jatuh di SETIAP bucket aging (belum jatuh tempo, 1–30, 31–60, >60).
     *
     * Dibuat langsung tanpa generator: periode lamanya tidak punya pembacaan
     * mentah, dan yang dibutuhkan laporan tunggakan hanyalah nominal beserta
     * tanggal jatuh temponya.
     */
    private function createAgingInvoices(): void
    {
        $target = [
            // [bulan ke belakang, hari lewat jatuh tempo, kode pelanggan]
            [2, 45, 'C-003'],   // bucket 31–60 hari
            [3, 80, 'C-007'],   // bucket > 60 hari
            [2, 40, 'C-008'],   // bucket 31–60 hari, pelanggan berbeda
        ];

        $generator = app(InvoiceGenerator::class);

        foreach ($target as [$monthsAgo, $overdueDays, $code]) {
            $customer = Customer::with('powerMeter', 'tariffGroup')->where('code', $code)->first();

            if (!$customer) {
                continue;
            }

            $month = now()->subMonths($monthsAgo)->startOfMonth();
            $period = $generator->periodFor($month->copy());

            $total = round(($customer->daya_kva * 9_800) + 2_400_000, -3);

            Invoice::create([
                'invoice_no' => sprintf('INV/%s/%03d', $month->format('Y/m'), $customer->id),
                'billing_period_id' => $period->id,
                'customer_id' => $customer->id,
                'power_meter_id' => $customer->power_meter_id,
                'customer_name' => $customer->name,
                'customer_address' => $customer->address,
                'meter_code' => $customer->powerMeter?->code,
                'tariff_group_code' => $customer->tariffGroup?->code,
                'period_start' => $month->toDateString(),
                'period_end' => $month->copy()->endOfMonth()->toDateString(),
                'kwh_lwbp' => round($total / 1_600, 2),
                'kwh_wbp' => round($total / 5_200, 2),
                'subtotal' => $total,
                'total_amount' => $total,
                'issue_date' => $month->copy()->addMonth()->startOfMonth()->toDateString(),
                'due_date' => now()->subDays($overdueDays)->toDateString(),
                'status' => 'overdue',
            ]);
        }
    }

    /**
     * Periode paling lama ditutup, supaya penjagaan "periode terkunci tidak
     * bisa digenerate ulang" punya contoh yang bisa dicoba.
     */
    private function closeOldestPeriod(): void
    {
        BillingPeriod::orderBy('period_start')
            ->first()
            ?->update([
                'status' => 'closed',
                'notes' => 'Ditutup setelah rekonsiliasi — contoh periode terkunci.',
            ]);
    }

    private function report(): void
    {
        $this->command?->info(sprintf(
            'Data demo siap: %d pelanggan, %d meter, %d invoice, %d pembayaran.',
            Customer::count(),
            PowerMeter::count(),
            Invoice::count(),
            InvoicePayment::count(),
        ));

        $this->command?->line('  Kondisi yang tercakup: sinyal kuat→tanpa data, meter 1 & 3 phase,');
        $this->command?->line('  perangkat offline & maintenance, meter tanpa pelanggan, pembacaan');
        $this->command?->line('  bolong & stand mundur, pelanggan tanpa email/meter/golongan,');
        $this->command?->line('  invoice draft→lunas→batal, cicilan, batch massal & impor, kuitansi');
        $this->command?->line('  terkirim & belum, tunggakan di seluruh bucket aging, periode tertutup.');
    }
}
