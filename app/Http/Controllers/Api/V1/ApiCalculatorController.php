<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SimulerBrutVersNetRequest;
use App\Http\Requests\Api\V1\SimulerNetVersBrutRequest;
use App\Http\Requests\PayrollValidation;
use App\Services\PayrollCalculatorService;
use Illuminate\Http\JsonResponse;

class ApiCalculatorController extends Controller
{
    public function __construct(private PayrollCalculatorService $calculator) {}

    public function simulerBrutVersNet(SimulerBrutVersNetRequest $request): JsonResponse
    {
        $input = $request->only(PayrollValidation::apiFields('gross_to_net'));

        $result = $this->calculator->calculer($input);
        $result['mode'] = 'gross_to_net';

        return response()->json($result);
    }

    public function simulerNetVersBrut(SimulerNetVersBrutRequest $request): JsonResponse
    {
        $input = $request->only(PayrollValidation::apiFields('net_to_gross'));

        $result = $this->calculator->resoudreDepuisNet($input);

        return response()->json($result);
    }

    public function parametres(): JsonResponse
    {
        return response()->json(config('payroll'));
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => config('app.version'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
