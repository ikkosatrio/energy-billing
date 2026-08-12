<?php

namespace App\Services\Billing;

use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ActivityLogger;
use App\Services\Tariff\TariffService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceGenerator
{
    public function __construct(
        private readonly UsageCalculator $usage,
        private readonly InvoiceCalculator $calculator,
        private readonly InvoiceNumberGenerator $numbers,
        private readonly TariffService $tariff,
    ) {
    }

    /**
     * Membuat atau menemukan periode billing untuk sebuah bulan.
     */
    public function periodFor(Carbon $month): BillingPeriod
    {
        $start = $month->copy()->startOfMonth();
        $cutOffDay = max(1, min(28, (int) setting('billing_cut_off_day', 1)));

        return BillingPeriod::firstOrCreate(
            ['code' => $start->format('Y-m')],
            [
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->endOfMonth()->toDateString(),
                // Cut-off jatuh di bulan berikutnya: periode harus selesai
                // dulu sebelum bisa ditagihkan. Tanggal dibatasi 1–28 supaya
                // selalu ada di setiap bulan.
                'cut_off_date' => $start->copy()->addMonth()->day($cutOffDay)->toDateString(),
                'status' => 'open',
            ],
        );
    }

    /**
     * Menerbitkan invoice untuk seluruh pelanggan yang siap ditagih pada
     * periode tersebut.
     *
     * Pelanggan yang sudah punya invoice di periode ini dilewati, kecuali
     * $regenerate = true — invoice berstatus draft akan dibuat ulang, yang
     * sudah terbit tidak pernah disentuh.
     *
     * @return array{created:int, skipped:int, failed:array<int, string>}
     */
    public function generate(BillingPeriod $period, bool $regenerate = false, ?array $customerIds = null): array
    {
        if ($period->isLocked()) {
            return ['created' => 0, 'skipped' => 0, 'failed' => ['Periode sudah ditutup.']];
        }

        $created = 0;
        $skipped = 0;
        $failed = [];

        $customers = Customer::billable()
            ->when($customerIds, fn ($q) => $q->whereIn('id', $customerIds))
            ->with(['powerMeter', 'tariffGroup'])
            ->get();

        foreach ($customers as $customer) {
            $existing = Invoice::where('billing_period_id', $period->id)
                ->where('customer_id', $customer->id)
                ->first();

            if ($existing && (!$regenerate || $existing->status !== 'draft')) {
                $skipped++;

                continue;
            }

            try {
                $this->generateFor($period, $customer, $existing);
                $created++;
            } catch (\Throwable $e) {
                report($e);
                $failed[] = "{$customer->name}: {$e->getMessage()}";
            }
        }

        $period->forceFill([
            'status' => 'generated',
            'generated_at' => now(),
            'generated_by' => auth()->id(),
        ])->save();

        ActivityLogger::log(
            'generate_invoice',
            $period,
            "Generate invoice periode {$period->code}: {$created} dibuat, {$skipped} dilewati",
        );

        return ['created' => $created, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Menerbitkan satu invoice. Seluruh angka dan identitas pelanggan
     * di-snapshot ke baris invoice — lihat komentar pada model Invoice.
     */
    public function generateFor(BillingPeriod $period, Customer $customer, ?Invoice $existing = null): Invoice
    {
        $start = $period->period_start->copy();
        $end = $period->period_end->copy();

        $usage = $this->usage->forPeriod($customer->powerMeter, $start, $end);
        $rate = $this->tariff->rateForPeriod($customer->tariffGroup, $end->toDateString());

        if (!$rate) {
            throw new \RuntimeException("golongan {$customer->tariffGroup->code} belum punya tarif yang berlaku pada {$end->toDateString()}");
        }

        $amounts = $this->calculator->calculate($customer, $rate, $usage);

        $issueDate = now();
        $dueDays = (int) setting('invoice_due_days', 14);

        $notes = [];
        if (!$usage['has_data']) {
            $notes[] = 'Tidak ada pembacaan meter pada periode ini — pemakaian tercatat 0.';
        }
        if ($usage['meter_reset']) {
            $notes[] = 'Stand meter mundur (kemungkinan reset/rollover). Pemakaian perlu dikoreksi manual.';
        }

        $payload = $amounts + [
            'billing_period_id' => $period->id,
            'customer_id' => $customer->id,
            'power_meter_id' => $customer->power_meter_id,
            'tariff_rate_id' => $rate->id,

            // Snapshot identitas — invoice lama tidak boleh ikut berubah
            // ketika data pelanggan diperbarui.
            'customer_name' => $customer->name,
            'customer_address' => $customer->address,
            'customer_npwp' => $customer->npwp,
            'meter_code' => $customer->powerMeter?->code,
            'tariff_group_code' => $customer->tariffGroup?->code,

            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays($dueDays)->toDateString(),
            'status' => 'draft',
            'notes' => $notes ? implode(' ', $notes) : null,
            'created_by' => auth()->id(),
        ];

        return DB::transaction(function () use ($existing, $payload, $period, $issueDate) {
            if ($existing) {
                // Nomor invoice yang sudah terbentuk dipertahankan agar
                // referensi yang terlanjur beredar tetap cocok.
                $existing->fill($payload)->save();

                return $existing;
            }

            $payload['invoice_no'] = $this->numbers->nextFor($period->id, $issueDate);

            return Invoice::create($payload);
        });
    }
}
