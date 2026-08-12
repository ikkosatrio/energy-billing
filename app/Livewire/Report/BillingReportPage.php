<?php

namespace App\Livewire\Report;

use App\Models\Customer;
use App\Services\Report\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Component;

class BillingReportPage extends Component
{
    public string $from = '';

    public string $to = '';

    public ?int $customerId = null;

    public function mount(): void
    {
        // Default 3 bulan terakhir agar tunggakan lama ikut terlihat.
        $this->from = now()->subMonths(2)->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render(ReportService $reports)
    {
        $rows = $reports->billing(Carbon::parse($this->from), Carbon::parse($this->to), $this->customerId);

        return view('livewire.report.billing-report-page', [
            'rows' => $rows,
            'totals' => $reports->totals($rows, ['total', 'paid', 'outstanding', 'total_kwh']),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'exportQuery' => http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'customer_id' => $this->customerId,
            ]),
        ]);
    }
}
