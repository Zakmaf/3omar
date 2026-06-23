<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollValidation;
use App\Services\PayrollCalculatorService;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function __construct(private PayrollCalculatorService $calculator) {}

    public function index()
    {
        return view('calculator.index', [
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels' => config('payroll.heures_sup.labels'),
        ]);
    }

    public function resultatIndisponible()
    {
        return redirect()
            ->route('calculator.index')
            ->with('calculator_notice', 'ui.calculator.result_direct_access_notice');
    }

    public function calculer(Request $request)
    {
        $mode = $request->input('mode', 'gross_to_net');

        $request->validate(
            PayrollValidation::webRules($mode),
            PayrollValidation::webMessages(),
        );

        $input = $request->only(PayrollValidation::webFields());

        try {
            if ($mode === 'net_to_gross') {
                $result = $this->calculator->resoudreDepuisNet($input);
            } else {
                $result = $this->calculator->calculer($input);
                $result['mode'] = 'gross_to_net';
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('calculator.index')
                ->withInput()
                ->withErrors(['calculator' => $e->getMessage()]);
        }

        return view('calculator.result', [
            'r' => $result,
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels' => config('payroll.heures_sup.labels'),
        ]);
    }
}
