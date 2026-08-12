<?php

namespace App\Livewire\Report;

use App\Models\Customer;
use App\Services\Report\ReportService;
use Illuminate\Support\Carbon;
use Livewire\Component;

class UsageReportPage extends Component
{
    public string $from = '';

    public string $to = '';

    public ?int $customerId = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();
    }

    public function render(ReportService $reports)
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        $rows = $reports->usage($from, $to, $this->customerId);

        return view('livewire.report.usage-report-page', [
            'rows' => $rows,
            'totals' => $reports->totals($rows, ['lwbp', 'wbp', 'total_kwh', 'billed']),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'exportQuery' => http_build_query([
                'from' => $this->from,
                'to' => $this->to,
                'customer_id' => $this->customerId,
            ]),
        ]);
    }
}
