<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SimulerBrutVersNetRequest;
use App\Http\Requests\Api\V1\SimulerNetVersBrutRequest;
use App\Services\PayrollCalculatorService;
use Illuminate\Http\JsonResponse;

class ApiCalculatorController extends Controller
{
    public function __construct(private PayrollCalculatorService $calculator) {}

    public function simulerBrutVersNet(SimulerBrutVersNetRequest $request): JsonResponse
    {
        $input = $request->only([
            'salaire_base', 'nb_annees_anciennete',
            'prime_bilan', 'prime_rendement', 'autres_primes',
            'type_frais_pro', 'nb_enfants', 'conjoint_charge',
            'cimr_taux', 'cimr_taux_employeur',
            'retraite_complementaire_mensuel', 'rc_part_employeur',
            'mutuelle_salarie', 'mutuelle_patronale',
            'autres_retenues', 'heures_sup', 'indemnites',
            'jours_travailles',
            'prime_scolarite', 'prime_aid', 'autres_avantages_cnss',
        ]);

        $result = $this->calculator->calculer($input);
        $result['mode'] = 'gross_to_net';

        return response()->json($result);
    }

    public function simulerNetVersBrut(SimulerNetVersBrutRequest $request): JsonResponse
    {
        $input = $request->only([
            'net_cible', 'nb_annees_anciennete',
            'prime_bilan', 'prime_rendement', 'autres_primes',
            'type_frais_pro', 'nb_enfants', 'conjoint_charge',
            'cimr_taux', 'cimr_taux_employeur',
            'retraite_complementaire_mensuel', 'rc_part_employeur',
            'mutuelle_salarie', 'mutuelle_patronale',
            'autres_retenues', 'heures_sup', 'indemnites',
            'jours_travailles',
            'prime_scolarite', 'prime_aid', 'autres_avantages_cnss',
        ]);

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
