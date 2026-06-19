<?php

namespace App\Http\Controllers;

use App\Services\PayrollCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $request->validate([
            'mode' => ['nullable', Rule::in(['gross_to_net', 'net_to_gross'])],
            'salaire_base' => [Rule::excludeIf($mode !== 'gross_to_net'), Rule::requiredIf($mode === 'gross_to_net'), 'nullable', 'numeric', 'min:0'],
            'net_cible' => [Rule::excludeIf($mode !== 'net_to_gross'), Rule::requiredIf($mode === 'net_to_gross'), 'nullable', 'numeric', 'min:0.01'],
            'nb_annees_anciennete' => 'nullable|integer|min:0|max:50',
            'prime_bilan' => 'nullable|numeric|min:0',
            'prime_rendement' => 'nullable|numeric|min:0',
            'autres_primes' => 'nullable|numeric|min:0',
            'type_frais_pro' => 'required|in:commun,journaliste,artiste',
            'nb_enfants' => 'nullable|integer|min:0|max:20',
            'cimr_taux' => 'nullable|numeric|min:0',
            'cimr_repartition' => ['nullable', Rule::in(config('payroll.cimr.repartitions'))],
            'cimr_taux_employeur' => 'nullable|numeric|min:0',
            'retraite_complementaire_mensuel' => 'nullable|numeric|min:0',
            'rc_part_employeur' => 'nullable|numeric|min:0',
            'mutuelle_salarie' => 'nullable|numeric|min:0',
            'mutuelle_patronale' => 'nullable|numeric|min:0',
            'autres_retenues' => 'nullable|numeric|min:0',
            'jours_travailles' => 'nullable|integer|min:1|max:31',
            'heures_sup.*.type' => 'required_with:heures_sup.*.nb_heures|in:semaine_diurne,semaine_nocturne,repos_diurne,repos_nocturne',
            'heures_sup.*.nb_heures' => 'nullable|numeric|min:0',
            'indemnites.*.type' => ['required_with:indemnites.*.montant', 'distinct', Rule::in(array_keys(config('payroll.indemnites')))],
            'indemnites.*.montant' => 'nullable|numeric|min:0',
            'prime_scolarite' => 'nullable|numeric|min:0',
            'prime_aid' => 'nullable|numeric|min:0',
            'autres_avantages_cnss' => 'nullable|numeric|min:0',
        ], [
            'salaire_base.required' => __('ui.validation.base_required'),
            'salaire_base.min' => __('ui.validation.base_positive'),
            'net_cible.required' => __('ui.validation.net_target_required'),
            'net_cible.min' => __('ui.validation.net_target_positive'),
            'type_frais_pro.in' => __('ui.validation.category_invalid'),
            'indemnites.*.type.distinct' => __('ui.validation.allowance_distinct'),
        ]);

        $input = $request->only([
            'mode', 'salaire_base', 'net_cible', 'nb_annees_anciennete',
            'prime_bilan', 'prime_rendement', 'autres_primes',
            'type_frais_pro', 'nb_enfants', 'conjoint_charge',
            'cimr_actif', 'cimr_taux', 'cimr_repartition', 'cimr_taux_employeur',
            'retraite_complementaire_mensuel', 'rc_part_employeur',
            'mutuelle_salarie', 'mutuelle_patronale',
            'autres_retenues', 'heures_sup', 'indemnites',
            'jours_travailles',
            'prime_scolarite', 'prime_aid', 'autres_avantages_cnss',
        ]);

        if ($mode === 'net_to_gross') {
            $result = $this->calculator->resoudreDepuisNet($input);
        } else {
            $result = $this->calculator->calculer($input);
            $result['mode'] = 'gross_to_net';
        }

        return view('calculator.result', [
            'r' => $result,
            'indemnites_config' => config('payroll.indemnites'),
            'hs_labels' => config('payroll.heures_sup.labels'),
        ]);
    }
}
