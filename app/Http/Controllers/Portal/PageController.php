<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;

/**
 * Pembungkus halaman portal — isinya komponen Livewire, sama polanya dengan
 * controller modul staf.
 */
class PageController extends Controller
{
    public function dashboard()
    {
        return view('portal.dashboard');
    }

    public function monitoring()
    {
        return view('portal.monitoring');
    }

    public function history()
    {
        return view('portal.history');
    }

    public function usage()
    {
        return view('portal.usage');
    }

    public function invoices()
    {
        return view('portal.invoices');
    }

    public function payments()
    {
        return view('portal.payments');
    }
}
