<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    public function index()
    {
        return view('billing.payments.index');
    }
}
