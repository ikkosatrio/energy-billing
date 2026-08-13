<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\MeterTariffSchedule;
use App\Models\PowerMeter;
use App\Models\TariffGroup;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Monitoring\DailyAggregationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data contoh untuk mencoba aplikasi: pelanggan, meter, pembacaan 2 bulan
 * terakhir, agregat harian, dan invoice bulan lalu.
 *
 * TIDAK dipanggil DatabaseSeeder — jalankan sendiri bila diperlukan:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /** Interval pembacaan yang dibuat (menit). */
    private const READING_INTERVAL = 30;

    private const CUSTOMERS = [
        // [kode, nama, alamat, PIC, telepon, meter, merek/model, lokasi, golongan, kVA, biaya beban, tgl tagih, kW rata2]
        ['C-001', 'PT Sinar Abadi Logistik', 'Kawasan Industri Jababeka Blok C-12, Cikarang', 'Budi Santoso', '0812-1122-3344',
            'AW9L-IRC38', 'Schneider|AW9L', 'Main Distribution Panel', 'I-3/TR', 630, 9_450_000, 1, 410],
        ['C-002', 'CV Mitra Cold Storage', 'Kawasan Industri Jababeka Blok C-14, Cikarang', 'Rina Wijaya', '0813-9087-1120',
            'AW9L-IRC41', 'Schneider|AW9L', 'Panel Gudang B', 'I-3/TR', 555, 8_325_000, 1, 295],
        ['C-003', 'PT Karya Pangan Sejahtera', 'Gudang B1, Jl. Industri Raya 8', 'Hendra Pratama', '0821-4455-9911',
            'SM630-B1', 'Eastron|SM630', 'Panel Gudang B1', 'B-3/TR', 345, 5_175_000, 5, 155],
        ['C-004', 'PT Anugerah Tekstil', 'Gudang B2, Jl. Industri Raya 8', 'Sari Melati', '0817-2233-8899',
            'SM630-B2', 'Eastron|SM630', 'Panel Gudang B2', 'B-3/TR', 240, 3_600_000, 5, 92],
        ['C-005', 'PT Jaya Distribusi', 'Gudang C1, Jl. Industri Raya 12', 'Agus Kurnia', '0878-6543-1200',
            'DTS353-C1', 'CHINT|DTS353', 'Panel Gudang C1', 'I-4/TM', 865, 0, 10, 220],
        ['C-006', 'PT Bahari Fresh', 'Gudang C2, Jl. Industri Raya 12', 'Dewi Anggraini', '0811-3344-5566',
            'DTS353-C2', 'CHINT|DTS353', 'Panel Gudang C2', 'B-3/TR', 240, 3_600_000, 10, 0],
    ];

    public function run(): void
    {
        if (Customer::exists()) {
            $this->command?->warn('Data pelanggan sudah ada — DemoDataSeeder dilewati.');

            return;
        }

        $this->call([RolePermissionSeeder::class, SettingSeeder::class, TariffGroupSeeder::class]);

        $meters = $this->createCustomers();
        $this->createReadings($meters);
        $this->aggregate();
        $this->createInvoices();

        $this->command?->info('Data demo siap: 6 pelanggan, pembacaan 2 bulan, dan invoice bulan lalu.');
    }

    /**
     * @return array<int, array{meter:PowerMeter, avgKw:float}>
     */
    private function createCustomers(): array
    {
        $result = [];

        foreach (self::CUSTOMERS as $row) {
            [$code, $name, $address, $pic, $phone, $meterCode, $device, $location,
                $groupCode, $kva, $biayaBeban, $billingDay, $avgKw] = $row;

            [$brand, $model] = explode('|', $device);

            $meter = PowerMeter::create([
                'code' => $meterCode,
                'name' => $location,
                'serial_no' => strtoupper(Str::random(10)),
                'brand' => $brand,
                'model' => $model,
                'location' => $location,
                'ct_ratio' => $kva > 500 ? '800/5' : '400/5',
                'multiplier' => 1,
                // Satu meter sengaja dibiarkan offline agar tampilan status
                // dan peringatan di dashboard ikut terlihat.
                'status' => $avgKw > 0 ? 'active' : 'maintenance',
                'installed_at' => now()->subYear()->toDateString(),
                'last_seen_at' => $avgKw > 0 ? now() : null,
            ]);

            $this->createSchedule($meter);

            Customer::create([
                'code' => $code,
                'name' => $name,
                'address' => $address,
                'pic_name' => $pic,
                'phone' => $phone,
                'email' => Str::slug(Str::before($name, ' ')).'@example.com',
                'power_meter_id' => $meter->id,
                'tariff_group_id' => TariffGroup::where('code', $groupCode)->value('id'),
                'daya_kva' => $kva,
                'biaya_beban_mode' => $biayaBeban > 0 ? 'flat' : 'per_kva',
                'biaya_beban' => $biayaBeban,
                'billing_day' => $billingDay,
                'contract_start' => now()->subYear()->toDateString(),
                'status' => 'active',
            ]);

            $result[] = ['meter' => $meter, 'avgKw' => (float) $avgKw];
        }

        return $result;
    }

    /**
     * Jadwal WBP 17:00–22:00, sisanya LWBP — pola umum tarif PLN.
     */
    private function createSchedule(PowerMeter $meter): void
    {
        $periods = [
            ['sequence' => 1, 'start_time' => '00:00', 'end_time' => '17:00', 'tariff_type' => 'LWBP'],
            ['sequence' => 2, 'start_time' => '17:00', 'end_time' => '22:00', 'tariff_type' => 'WBP'],
            ['sequence' => 3, 'start_time' => '22:00', 'end_time' => '00:00', 'tariff_type' => 'LWBP'],
        ];

        foreach ($periods as $period) {
            MeterTariffSchedule::create($period + ['power_meter_id' => $meter->id]);
        }
    }

    /**
     * Membuat pembacaan dua bulan terakhir dengan stand yang terus menaik.
     *
     * @param  array<int, array{meter:PowerMeter, avgKw:float}>  $meters
     */
    private function createReadings(array $meters): void
    {
        $start = now()->subMonth()->startOfMonth();
        $end = now();

        foreach ($meters as $entry) {
            $meter = $entry['meter'];
            $avgKw = $entry['avgKw'];

            if ($avgKw <= 0) {
                continue;
            }

            $standLwbp = 1_200_000.0;
            $standWbp = 400_000.0;
            $rows = [];
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                $hour = (int) $cursor->format('H');
                $isWbp = $hour >= 17 && $hour < 22;
                // Beban malam lebih rendah, siang lebih tinggi — supaya chart
                // per jam punya bentuk yang wajar.
                $factor = match (true) {
                    $hour < 6 => 0.55,
                    $hour < 17 => 1.0,
                    $hour < 22 => 0.85,
                    default => 0.65,
                };

                $kw = $avgKw * $factor * (0.9 + (($cursor->timestamp % 17) / 85));
                $kwh = $kw * (self::READING_INTERVAL / 60);

                $isWbp ? $standWbp += $kwh : $standLwbp += $kwh;

                $rows[] = [
                    'power_meter_id' => $meter->id,
                    'read_at' => $cursor->toDateTimeString(),
                    'stand_lwbp' => round($standLwbp, 2),
                    'stand_wbp' => round($standWbp, 2),
                    'active_power_kw' => round($kw, 2),
                    'voltage_r' => 380 + ($cursor->timestamp % 5),
                    'voltage_s' => 379 + ($cursor->timestamp % 4),
                    'voltage_t' => 381 - ($cursor->timestamp % 3),
                    'current_r' => round($kw * 1.5, 2),
                    'current_s' => round($kw * 1.48, 2),
                    'current_t' => round($kw * 1.52, 2),
                    'power_factor' => 0.92 + (($cursor->timestamp % 6) / 100),
                    'frequency' => 50,
                    'source' => 'api',
                    'created_at' => $cursor->toDateTimeString(),
                ];

                $cursor->addMinutes(self::READING_INTERVAL);
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('meter_readings')->insert($chunk);
            }
        }
    }

    private function aggregate(): void
    {
        $service = app(DailyAggregationService::class);
        $cursor = now()->subMonth()->startOfMonth();

        while ($cursor->lte(now())) {
            $service->aggregateAll($cursor->copy());
            $cursor->addDay();
        }
    }

    private function createInvoices(): void
    {
        $generator = app(InvoiceGenerator::class);
        $period = $generator->periodFor(Carbon::parse(now()->subMonth()->format('Y-m').'-01'));

        $generator->generate($period);
    }
}
