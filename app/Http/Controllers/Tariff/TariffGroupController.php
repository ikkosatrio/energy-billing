<?php

namespace App\Http\Controllers\Tariff;

use App\Http\Controllers\Controller;

class TariffGroupController extends Controller
{
    public function index()
    {
        return view('tariff.groups.index');
    }
}
