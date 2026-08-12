<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

class CustomerController extends Controller
{
    public function index()
    {
        return view('master.customers.index');
    }
}
