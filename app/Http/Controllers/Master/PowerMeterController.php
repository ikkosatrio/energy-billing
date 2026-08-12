<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

class PowerMeterController extends Controller
{
    public function index()
    {
        return view('master.meters.index');
    }
}
