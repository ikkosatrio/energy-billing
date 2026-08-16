<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

class TrialDataWipeController extends Controller
{
    public function index()
    {
        return view('system.trial-data.index');
    }
}
