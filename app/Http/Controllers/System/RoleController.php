<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

class RoleController extends Controller
{
    public function index()
    {
        return view('system.roles.index');
    }
}
