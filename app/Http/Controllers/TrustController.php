<?php

namespace App\Http\Controllers;

class TrustController extends Controller
{
    public function index()
    {
        $matrix = [
            [
                'rule' => 'CNSS salarié',
                'taux' => config('payroll.cnss.taux') * 100 .'%',
                'plafond' => number_format(config('payroll.cnss.plafond'), 0, ',', ' ').' MAD/mois',
                'source' => 'Dahir 1-72-184',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'CNSS patronal',
                'taux' => config('payroll.cnss.taux_patronal') * 100 .'%',
                'plafond' => number_format(config('payroll.cnss.plafond'), 0, ',', ' ').' MAD/mois',
                'source' => 'Dahir 1-72-184',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'AMO salarié',
                'taux' => config('payroll.amo.taux') * 100 .'%',
                'plafond' => 'Sans plafond',
                'source' => 'Loi 65-00',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'AMO patronale',
                'taux' => config('payroll.amo.taux_patronal') * 100 .'%',
                'plafond' => 'Sans plafond',
                'source' => 'Loi 65-00',
                'derniere_verification' => '2025-10',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'IR - Barème progressif',
                'taux' => '0% à 37%',
                'plafond' => '6 tranches',
                'source' => 'Art. 73 CGI, LF 50-25',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'Frais professionnels',
                'taux' => '25% ou 35% (ou 40%/45%)',
                'plafond' => number_format(config('payroll.frais_pro.commun.bas.plafond'), 0, ',', ' ').' MAD/mois',
                'source' => 'Art. 59 I-A CGI',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => "Prime d'ancienneté",
                'taux' => '5% à 25%',
                'plafond' => 'Calculée sur salaire de base',
                'source' => 'Art. 350 Code du Travail',
                'derniere_verification' => '2025-09',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'Allocations familiales (patronal)',
                'taux' => config('payroll.allocations_familiales.taux_patronal') * 100 .'%',
                'plafond' => 'Sans plafond',
                'source' => 'Code du Travail',
                'derniere_verification' => '2025-09',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'Taxe de formation pro. (TFP)',
                'taux' => config('payroll.taxe_formation.taux_patronal') * 100 .'%',
                'plafond' => 'Sans plafond',
                'source' => 'Code du Travail',
                'derniere_verification' => '2025-09',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'CIMR salarié',
                'taux' => '3% à 10% (libre)',
                'plafond' => 'Sans plafond',
                'source' => 'Art. 28-III CGI',
                'derniere_verification' => '2025-09',
                'confiance' => 'medium',
                'couverture' => 'partial',
            ],
            [
                'rule' => 'Indemnités exonérées',
                'taux' => 'Plafonds par type',
                'plafond' => 'Arrêté 1314-25',
                'source' => 'BO n° 7443 (29/09/2025)',
                'derniere_verification' => '2025-10',
                'confiance' => 'medium',
                'couverture' => 'partial',
            ],
            [
                'rule' => 'SMIG 2026',
                'taux' => number_format(config('payroll.smig.horaire'), 2, ',', ' ').' MAD/h',
                'plafond' => number_format(config('payroll.smig.mensuel'), 0, ',', ' ').' MAD/mois',
                'source' => 'Décret 2.25.983',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'Charges de famille (IR)',
                'taux' => number_format(config('payroll.charges_famille.par_personne'), 0, ',', ' ').' MAD/pers./mois',
                'plafond' => number_format(config('payroll.charges_famille.plafond'), 0, ',', ' ').' MAD/mois max',
                'source' => 'Art. 74 CGI',
                'derniere_verification' => '2026-01',
                'confiance' => 'high',
                'couverture' => 'full',
            ],
            [
                'rule' => 'Retraite complémentaire (RC)',
                'taux' => 'Libre',
                'plafond' => '50% du SBI annuel',
                'source' => 'Art. 28-IV CGI',
                'derniere_verification' => '2025-09',
                'confiance' => 'medium',
                'couverture' => 'partial',
            ],
        ];

        return view('trust.index', compact('matrix'));
    }
}
