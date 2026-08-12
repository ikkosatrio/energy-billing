<?php

namespace Database\Seeders;

use App\Models\TariffGroup;
use Illuminate\Database\Seeder;

/**
 * Contoh golongan tarif beserta tarif awalnya. Angka di sini hanya titik
 * mulai — sesuaikan lewat halaman Golongan & Tarif. Mengubah tarif nantinya
 * berarti MENAMBAH baris tariff_rates baru, bukan mengedit yang ini.
 */
class TariffGroupSeeder extends Seeder
{
    private const GROUPS = [
        ['I-3/TR', 'I-3 / Tegangan Rendah', 1114.74, 1560.64, 40000],
        ['I-4/TM', 'I-4 / Tegangan Menengah', 1035.78, 1450.09, 32000],
        ['B-3/TR', 'B-3 / Tegangan Rendah', 1035.78, 1450.09, 40000],
    ];

    public function run(): void
    {
        foreach (self::GROUPS as [$code, $name, $lwbp, $wbp, $beban]) {
            $group = TariffGroup::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true],
            );

            // effective_to NULL = berlaku sampai ada tarif pengganti.
            $group->rates()->firstOrCreate(
                ['effective_from' => now()->startOfYear()->toDateString()],
                [
                    'rate_lwbp' => $lwbp,
                    'rate_wbp' => $wbp,
                    'rate_beban_per_kva' => $beban,
                    'effective_to' => null,
                    'notes' => 'Tarif awal hasil seeder — sesuaikan dengan tarif yang berlaku.',
                ],
            );
        }
    }
}
