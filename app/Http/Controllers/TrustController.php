<?php

namespace App\Http\Controllers;

class TrustController extends Controller
{
    public function index()
    {
        $money = fn ($amount, $suffix = ' MAD/mois') => number_format($amount, 0, ',', ' ').$suffix;

        $matrix = [
            [
                'rule' => __('ui.trust.matrix_rows.cnss_employee.rule'),
                'taux' => config('payroll.cnss.taux') * 100 .'%',
                'plafond' => $money(config('payroll.cnss.plafond')),
                'source' => 'Dahir 1-72-184',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.cnss_employer.rule'),
                'taux' => config('payroll.cnss.taux_patronal') * 100 .'%',
                'plafond' => $money(config('payroll.cnss.plafond')),
                'source' => 'Dahir 1-72-184',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.amo_employee.rule'),
                'taux' => config('payroll.amo.taux') * 100 .'%',
                'plafond' => __('ui.trust.matrix_rows.no_ceiling'),
                'source' => 'Loi 65-00',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.amo_employer.rule'),
                'taux' => config('payroll.amo.taux_patronal') * 100 .'%',
                'plafond' => __('ui.trust.matrix_rows.no_ceiling'),
                'source' => 'Loi 65-00',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.ir_progressive.rule'),
                'taux' => __('ui.trust.matrix_rows.ir_progressive.value'),
                'plafond' => __('ui.trust.matrix_rows.ir_progressive.ceiling'),
                'source' => 'Art. 73 CGI, LF 50-25',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.professional_expenses.rule'),
                'taux' => __('ui.trust.matrix_rows.professional_expenses.value'),
                'plafond' => $money(config('payroll.frais_pro.commun.bas.plafond')),
                'source' => 'Art. 59 I-A CGI',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.seniority_bonus.rule'),
                'taux' => __('ui.trust.matrix_rows.seniority_bonus.value'),
                'plafond' => __('ui.trust.matrix_rows.seniority_bonus.ceiling'),
                'source' => 'Art. 350 Code du Travail',
                'derniere_verification' => '2025-09',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.family_allowances.rule'),
                'taux' => config('payroll.allocations_familiales.taux_patronal') * 100 .'%',
                'plafond' => __('ui.trust.matrix_rows.no_ceiling'),
                'source' => 'Code du Travail',
                'derniere_verification' => '2025-09',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.tfp.rule'),
                'taux' => config('payroll.taxe_formation.taux_patronal') * 100 .'%',
                'plafond' => __('ui.trust.matrix_rows.no_ceiling'),
                'source' => 'Code du Travail',
                'derniere_verification' => '2025-09',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.cimr_employee.rule'),
                'taux' => __('ui.trust.matrix_rows.cimr_employee.value'),
                'plafond' => __('ui.trust.matrix_rows.no_ceiling'),
                'source' => 'Art. 28-III CGI',
                'derniere_verification' => '2025-09',
                'confiance' => 'medium',
                'couverture' => 'partial',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.exempt_allowances.rule'),
                'taux' => __('ui.trust.matrix_rows.exempt_allowances.value'),
                'plafond' => 'Arrêté 1314-25',
                'source' => 'BO n° 7443 (29/09/2025)',
                'derniere_verification' => '2025-10',
                'confiance' => 'medium',
                'couverture' => 'partial',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.smig.rule'),
                'taux' => number_format(config('payroll.smig.horaire'), 2, ',', ' ').' MAD/h',
                'plafond' => $money(config('payroll.smig.mensuel')),
                'source' => 'Décret 2.25.983',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.family_charges.rule'),
                'taux' => number_format(config('payroll.charges_famille.par_personne'), 0, ',', ' ').' MAD/pers./mois',
                'plafond' => number_format(config('payroll.charges_famille.plafond'), 0, ',', ' ').' MAD/mois max',
                'source' => 'Art. 74 CGI',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => __('ui.trust.matrix_rows.complementary_retirement.rule'),
                'taux' => __('ui.trust.matrix_rows.complementary_retirement.value'),
                'plafond' => __('ui.trust.matrix_rows.complementary_retirement.ceiling'),
                'source' => 'Art. 28-IV CGI',
                'derniere_verification' => '2025-09',
                'confiance' => 'medium',
                'couverture' => 'partial',
            ],
        ];

        return view('trust.index', compact('matrix'));
    }
}
