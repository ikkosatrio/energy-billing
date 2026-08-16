<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index()
    {
        return view('dashboard', [
            'usage' => $this->dashboard->monthlyUsage(),
            'billing' => $this->dashboard->currentBilling(),
            'meterStatus' => $this->dashboard->meterStatus(),
            'outstanding' => $this->dashboard->outstanding(),
        ]);
    }
}
