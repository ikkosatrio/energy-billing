<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;

class RealtimeController extends Controller
{
    public function index()
    {
        return view('monitoring.realtime.index');
    }
}
