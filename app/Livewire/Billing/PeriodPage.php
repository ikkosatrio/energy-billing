<?php

namespace App\Livewire\Billing;

use App\Models\BillingPeriod;
use App\Models\Customer;
use App\Services\ActivityLogger;
use App\Services\Billing\InvoiceGenerator;
use Illuminate\Support\Carbon;
use Livewire\Component;

class PeriodPage extends Component
{
    /** Bulan yang akan digenerate, format Y-m. */
    public string $month = '';

    public bool $regenerate = false;

    /** Hasil generate terakhir, ditampilkan sebagai ringkasan. */
    public ?array $result = null;

    public function mount(): void
    {
        // Default menagih bulan yang sudah selesai.
        $this->month = now()->subMonth()->format('Y-m');
    }

    public function generate(InvoiceGenerator $generator): void
    {
        $this->authorize('invoice.generate');

        $period = $generator->periodFor(Carbon::parse($this->month.'-01'));

        if ($period->isLocked()) {
            $this->dispatch('toast', type: 'error', message: 'Periode sudah ditutup dan tidak bisa digenerate ulang.');

            return;
        }

        $this->result = $generator->generate($period, $this->regenerate);

        $message = "{$this->result['created']} invoice dibuat, {$this->result['skipped']} dilewati.";
        $this->dispatch('toast',
            type: $this->result['failed'] ? 'warning' : 'success',
            message: $message,
        );
    }

    /**
     * Menutup periode: invoice di dalamnya tidak bisa lagi digenerate ulang.
     * Dipakai setelah tagihan diverifikasi dan dikirim.
     */
    public function close(int $id): void
    {
        $this->authorize('invoice.generate');

        $period = BillingPeriod::findOrFail($id);
        $period->update(['status' => 'closed']);

        ActivityLogger::log('close_period', $period, "Tutup periode {$period->code}");
        $this->dispatch('toast', type: 'success', message: "Periode {$period->code} ditutup.");
    }

    public function reopen(int $id): void
    {
        $this->authorize('invoice.generate');

        $period = BillingPeriod::findOrFail($id);
        $period->update(['status' => 'generated']);

        ActivityLogger::log('reopen_period', $period, "Buka kembali periode {$period->code}");
        $this->dispatch('toast', type: 'warning', message: "Periode {$period->code} dibuka kembali.");
    }

    public function render()
    {
        return view('livewire.billing.period-page', [
            'periods' => BillingPeriod::withCount('invoices')
                ->withSum('invoices as total_amount_sum', 'total_amount')
                ->orderByDesc('period_start')
                ->limit(24)
                ->get(),
            'billableCount' => Customer::billable()->count(),
            // Pelanggan aktif yang belum lengkap datanya tidak akan ikut
            // ditagih — ditampilkan agar tidak diam-diam terlewat.
            'incompleteCount' => Customer::active()
                ->where(fn ($q) => $q->whereNull('power_meter_id')->orWhereNull('tariff_group_id'))
                ->count(),
            'defaultBillingDay' => (int) setting('billing_cut_off_day', 1),
        ]);
    }
}
