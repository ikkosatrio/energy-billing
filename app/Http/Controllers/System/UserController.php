<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;

class UserController extends Controller
{
    public function index()
    {
        return view('system.users.index');
    }
}
