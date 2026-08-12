<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Billing\InvoiceGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Dijalankan scheduler setiap hari.
 *
 * Tanggal tagih boleh berbeda per pelanggan (kolom customers.billing_day,
 * kosong = ikut setting global), jadi perintah ini yang menentukan pelanggan
 * mana yang jatuh tempo hari ini — bukan jadwal cron-nya.
 */
class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate
                            {--month= : Periode yang ditagih (Y-m). Default: bulan lalu.}
                            {--all : Abaikan tanggal tagih, proses seluruh pelanggan.}
                            {--regenerate : Buat ulang invoice yang masih berstatus draft.}';

    protected $description = 'Menerbitkan invoice untuk pelanggan yang jatuh tempo penagihan hari ini';

    public function handle(InvoiceGenerator $generator): int
    {
        // Yang ditagih adalah bulan yang SUDAH selesai, karena pemakaiannya
        // dihitung dari stand akhir periode.
        $month = $this->option('month')
            ? Carbon::parse($this->option('month').'-01')
            : now()->subMonth();

        $period = $generator->periodFor($month);

        if ($period->isLocked()) {
            $this->warn("Periode {$period->code} sudah ditutup. Tidak ada yang diproses.");

            return self::SUCCESS;
        }

        $customerIds = $this->option('all') ? null : $this->customersDueToday();

        if ($customerIds !== null && empty($customerIds)) {
            $this->line('Tidak ada pelanggan yang jatuh tempo penagihan hari ini.');

            return self::SUCCESS;
        }

        $result = $generator->generate($period, (bool) $this->option('regenerate'), $customerIds);

        $this->info("Periode {$period->code}: {$result['created']} invoice dibuat, {$result['skipped']} dilewati.");

        foreach ($result['failed'] as $message) {
            $this->error("Gagal — {$message}");
        }

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * ID pelanggan yang tanggal tagihnya jatuh hari ini.
     *
     * @return array<int>
     */
    private function customersDueToday(): array
    {
        $today = now()->day;
        $default = (int) setting('billing_cut_off_day', 1);

        return Customer::billable()
            ->get(['id', 'billing_day'])
            ->filter(fn ($customer) => ($customer->billing_day ?? $default) === $today)
            ->pluck('id')
            ->all();
    }
}
