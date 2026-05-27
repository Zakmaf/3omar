<?php

namespace App\Http\Controllers;

class DocumentationController extends Controller
{
    public function index()
    {
        return view('documentation.index', [
            'payroll'           => config('payroll'),
            'baremes'           => config('payroll.ir.baremes'),
            'indemnites_config' => config('payroll.indemnites'),
        ]);
    }
}
