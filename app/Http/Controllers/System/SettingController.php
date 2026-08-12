<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

class SettingController extends Controller
{
    public function index()
    {
        return view('system.settings.index');
    }
}
