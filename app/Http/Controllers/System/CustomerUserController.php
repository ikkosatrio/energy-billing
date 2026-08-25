<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

class CustomerUserController extends Controller
{
    public function index()
    {
        return view('system.customer-users.index');
    }
}
