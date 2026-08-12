<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;

class BillingPeriodController extends Controller
{
    public function index()
    {
        return view('billing.periods.index');
    }
}
