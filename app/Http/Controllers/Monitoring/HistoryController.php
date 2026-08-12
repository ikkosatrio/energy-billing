<?php

namespace App\Http\Controllers\Monitoring;

use App\Http\Controllers\Controller;

class HistoryController extends Controller
{
    public function index()
    {
        return view('monitoring.history.index');
    }
}
