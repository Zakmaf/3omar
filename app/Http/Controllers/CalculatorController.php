<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PayrollCalculatorService;

class CalculatorController extends Controller
{
    public function __construct(private PayrollCalculatorService $calculator) {}

    public function index()
    {
        return view('calculator.index', [
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels'         => config('payroll.heures_sup.labels'),
        ]);
    }

    public function calculer(Request $request)
    {
        $cimrMin = (int) (config('payroll.cimr.taux_min') * 100);
        $cimrMax = (int) (config('payroll.cimr.taux_max') * 100);

        $request->validate([
            'salaire_base'                    => 'required|numeric|min:0',
            'nb_annees_anciennete'            => 'nullable|integer|min:0|max:50',
            'prime_bilan'                     => 'nullable|numeric|min:0',
            'prime_rendement'                 => 'nullable|numeric|min:0',
            'autres_primes'                   => 'nullable|numeric|min:0',
            'type_frais_pro'                  => 'required|in:commun,journaliste,artiste',
            'nb_enfants'                      => 'nullable|integer|min:0|max:20',
            'cimr_taux'                       => "nullable|numeric|min:{$cimrMin}|max:{$cimrMax}",
            'retraite_complementaire_mensuel' => 'nullable|numeric|min:0',
            'mutuelle_salarie'                => 'nullable|numeric|min:0',
            'mutuelle_patronale'              => 'nullable|numeric|min:0',
            'autres_retenues'                 => 'nullable|numeric|min:0',
            'heures_sup.*.type'               => 'required_with:heures_sup.*.nb_heures|in:semaine_diurne,semaine_nocturne,repos_diurne,repos_nocturne',
            'heures_sup.*.nb_heures'          => 'nullable|numeric|min:0',
            'indemnites.*.type'               => 'required_with:indemnites.*.montant|string',
            'indemnites.*.montant'            => 'nullable|numeric|min:0',
        ], [
            'salaire_base.required' => 'Le salaire de base est obligatoire.',
            'salaire_base.min'      => 'Le salaire de base doit être positif.',
            'type_frais_pro.in'     => 'Catégorie professionnelle invalide.',
            'cimr_taux.min'         => "Le taux CIMR doit être au minimum {$cimrMin}%.",
            'cimr_taux.max'         => "Le taux CIMR ne peut pas dépasser {$cimrMax}%.",
        ]);

        $input = $request->only([
            'salaire_base', 'nb_annees_anciennete',
            'prime_bilan', 'prime_rendement', 'autres_primes',
            'type_frais_pro', 'nb_enfants', 'conjoint_charge',
            'cimr_actif', 'cimr_taux',
            'retraite_complementaire_mensuel',
            'mutuelle_salarie', 'mutuelle_patronale',
            'autres_retenues', 'heures_sup', 'indemnites',
        ]);

        $result = $this->calculator->calculer($input);

        return view('calculator.result', [
            'r'                 => $result,
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels'         => config('payroll.heures_sup.labels'),
        ]);
    }
}
