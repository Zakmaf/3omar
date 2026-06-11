<?php

return [

    'year' => 2026,
    'regulation' => 'Loi de Finances 50-25',

    // =========================================================================
    // SMIG 2026 — Décret n° 2.25.983
    // =========================================================================
    'smig' => [
        'horaire' => 17.92,
        'mensuel' => 3422.72,
        'heures_legales' => 191,
    ],

    // =========================================================================
    // CNSS — Dahir n° 1-72-184 du 27 juillet 1972
    // Plafond mensuel de l'assiette : 6 000 MAD
    // =========================================================================
    'cnss' => [
        'taux' => 0.0448,   // Salarié (CT 0,52% + LT 3,96%)
        'taux_patronal' => 0.0898,   // Employeur (CT 1,05% + LT 7,93%)
        'plafond' => 6000,
    ],

    // =========================================================================
    // AMO — Loi n° 65-00 (couverture médicale de base)
    // Sans plafond d'assiette
    // =========================================================================
    'amo' => [
        'taux' => 0.0226,   // Salarié
        'taux_patronal' => 0.0411,   // Employeur
    ],

    // =========================================================================
    // Allocations familiales — Part patronale uniquement
    // =========================================================================
    'allocations_familiales' => [
        'taux_patronal' => 0.0640,
    ],

    // =========================================================================
    // Taxe de Formation Professionnelle (TFP) — Part patronale uniquement
    // =========================================================================
    'taxe_formation' => [
        'taux_patronal' => 0.0160,
    ],

    // =========================================================================
    // CIMR — Art. 28-III CGI
    // Taux salarié librement choisi, 100 % déductible IR
    // =========================================================================
    'cimr' => [
        'taux_min' => 0.03,
        'taux_max' => 0.10,
    ],

    // =========================================================================
    // Frais Professionnels — Art. 59 I-A CGI
    // Déduits de l'assiette IR (non remboursés au salarié)
    // =========================================================================
    // Plafond annuel unique : 35 000 MAD/an ≈ 2 916,67 MAD/mois (LF 2023, reconduit en 2026)
    'frais_pro' => [
        'seuil_mensuel' => 6500,
        'commun' => [
            'bas' => ['taux' => 0.35, 'plafond' => 2916.67],
            'haut' => ['taux' => 0.25, 'plafond' => 2916.67],
        ],
        'journaliste' => ['taux' => 0.45, 'plafond' => 2916.67],
        'artiste' => ['taux' => 0.40, 'plafond' => 2916.67],
    ],

    // =========================================================================
    // Barème IR annuel 2026 — Art. 73 CGI (Loi de Finances 50-25)
    // Formule : IR annuel = RNI_annuel × taux − deduction
    // Méthode d'annualisation : RNI mensuel × 12 → IR annuel → ÷ 12
    // =========================================================================
    'ir' => [
        'nb_mois' => 12,
        'baremes' => [
            ['min' => 0, 'max' => 40000, 'taux' => 0.00, 'deduction' => 0],
            ['min' => 40001, 'max' => 60000, 'taux' => 0.10, 'deduction' => 4000],
            ['min' => 60001, 'max' => 80000, 'taux' => 0.20, 'deduction' => 10000],
            ['min' => 80001, 'max' => 100000, 'taux' => 0.30, 'deduction' => 18000],
            ['min' => 100001, 'max' => 180000, 'taux' => 0.34, 'deduction' => 22000],
            ['min' => 180001, 'max' => null, 'taux' => 0.37, 'deduction' => 27400],
        ],
    ],

    // =========================================================================
    // Charges de famille — Art. 74 CGI
    // =========================================================================
    'charges_famille' => [
        'par_personne' => 50.00,
        'plafond' => 300.00,
    ],

    // =========================================================================
    // Heures supplémentaires — Art. 201 Code du Travail (Loi n° 65-99)
    // Taux horaire de référence = salaire_base / 191 h
    // =========================================================================
    'heures_sup' => [
        'majorations' => [
            'semaine_diurne' => 0.25,
            'semaine_nocturne' => 0.50,
            'repos_diurne' => 0.50,
            'repos_nocturne' => 1.00,
        ],
        'labels' => [
            'semaine_diurne' => 'Jour ouvrable — diurne (6h–21h) +25%',
            'semaine_nocturne' => 'Jour ouvrable — nocturne (21h–6h) +50%',
            'repos_diurne' => 'Repos/Férié — diurne +50%',
            'repos_nocturne' => 'Repos/Férié — nocturne +100%',
        ],
    ],

    // =========================================================================
    // Indemnités traitées comme exonérées par le simulateur
    // Hypothèses à valider selon la nature de l'indemnité et la situation réelle.
    // `par_jour => true` : le plafond est journalier (× jours travaillés du mois)
    // =========================================================================
    'jours_travailles_defaut' => 26,

    'indemnites' => [
        'transport' => ['label' => 'Indemnité de transport',       'base_salaire' => false, 'montant' => 500, 'pct' => null],
        // Panier : plafond journalier = 2 × SMIG horaire (2 × 17,92 = 35,84 MAD/jour travaillé)
        'panier' => ['label' => 'Indemnité de panier/repas',    'base_salaire' => false, 'montant' => 35.84, 'pct' => null, 'par_jour' => true],
        'representation' => ['label' => 'Indemnité de représentation',  'base_salaire' => true,  'montant' => null, 'pct' => 0.10],
        'salissure' => ['label' => 'Indemnité de salissure',       'base_salaire' => false, 'montant' => 200, 'pct' => null],
        'outillage' => ['label' => "Indemnité d'outillage",        'base_salaire' => false, 'montant' => 200, 'pct' => null],
        'vestimentaire' => ['label' => 'Indemnité vestimentaire',      'base_salaire' => false, 'montant' => 200, 'pct' => null],
        'logement' => ['label' => 'Indemnité de logement',        'base_salaire' => true,  'montant' => null, 'pct' => 0.10],
        'voiture_fonction' => ['label' => 'Avantage voiture de fonction', 'base_salaire' => true,  'montant' => null, 'pct' => 0.10],
    ],

    // =========================================================================
    // Prime d'ancienneté — Art. 350 Code du Travail (Loi n° 65-99)
    // Calculée sur le salaire de base mensuel brut
    // =========================================================================
    'anciennete' => [
        'tranches' => [
            ['min_annees' => 2, 'max_annees' => 4, 'taux' => 0.05],
            ['min_annees' => 5, 'max_annees' => 11, 'taux' => 0.10],
            ['min_annees' => 12, 'max_annees' => 19, 'taux' => 0.15],
            ['min_annees' => 20, 'max_annees' => 24, 'taux' => 0.20],
            ['min_annees' => 25, 'max_annees' => null, 'taux' => 0.25],
        ],
    ],

    // =========================================================================
    // Retraite complémentaire (Bancassurance) — Art. 28-IV CGI
    // Déduction fiscale simulée ; le versement n'est pas retenu du net à payer.
    // =========================================================================
    'retraite_complementaire' => [
        'deduction_ir_max_pct' => 0.50,
        'article' => 'Art. 28-IV CGI',
    ],

];
