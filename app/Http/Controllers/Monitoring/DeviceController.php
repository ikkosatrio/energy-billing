<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;

class DeviceController extends Controller
{
    public function index()
    {
        return view('monitoring.devices.index');
    }
}
