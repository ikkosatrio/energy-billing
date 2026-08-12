<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

class ActivityLogController extends Controller
{
    public function index()
    {
        return view('system.activity-logs.index');
    }
}
