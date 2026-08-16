<?php

namespace App\Console\Commands;

use App\Models\PowerMeter;
use App\Models\PowerMeterStatus;
use Illuminate\Console\Command;

/**
 * Menyegarkan "denyut" perangkat pada data demo.
 *
 * Meter dianggap offline bila tidak mengirim data lebih dari 5 menit
 * (PowerMeter::OFFLINE_AFTER_MINUTES). Pada data demo tidak ada gateway yang
 * benar-benar mengirim, jadi beberapa menit setelah seeder dijalankan SELURUH
 * meter berubah merah — aplikasinya sebetulnya benar, tapi demo dan tangkapan
 * layar jadi terlihat seperti rusak.
 *
 * Jalankan tepat sebelum demo atau sebelum memotret layar:
 *
 *   php artisan demo:heartbeat
 *
 * Meter yang sengaja dibuat offline atau maintenance di seeder tidak ikut
 * disegarkan — kondisi itu memang bagian dari contoh data.
 */
class RefreshDemoHeartbeat extends Command
{
    protected $signature = 'demo:heartbeat';

    protected $description = 'Segarkan last_seen_at data demo supaya meter aktif kembali tampil online';

    public function handle(): int
    {
        $meters = PowerMeter::where('status', 'active')
            // Meter yang belum pernah lapor sengaja dibiarkan: itu contoh
            // perangkat baru yang belum dipasang.
            ->whereNotNull('last_seen_at')
            ->get();

        if ($meters->isEmpty()) {
            $this->warn('Tidak ada meter aktif yang perlu disegarkan.');

            return self::SUCCESS;
        }

        $offline = 0;
        $online = 0;

        foreach ($meters as $meter) {
            // Satu meter memang dicontohkan offline — jaraknya dari sekarang
            // dipertahankan, bukan ikut dimajukan.
            $sengajaOffline = $meter->last_seen_at->lt(now()->subHour());

            $waktu = $sengajaOffline ? now()->subHours(3) : now();

            $meter->forceFill(['last_seen_at' => $waktu])->save();
            PowerMeterStatus::where('power_meter_id', $meter->id)
                ->update(['read_at' => $waktu]);

            $sengajaOffline ? $offline++ : $online++;
        }

        $this->info("Denyut disegarkan: {$online} meter online, {$offline} sengaja dibiarkan offline.");

        return self::SUCCESS;
    }
}
