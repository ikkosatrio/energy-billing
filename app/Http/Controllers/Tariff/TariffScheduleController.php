<?php

namespace App\Http\Controllers\Tariff;

use App\Http\Controllers\Controller;

class TariffScheduleController extends Controller
{
    public function index()
    {
        return view('tariff.schedules.index');
    }
}
