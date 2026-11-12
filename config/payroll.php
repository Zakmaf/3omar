<?php

// =========================================================================
// Valeurs 2026 des clés historiques ir.baremes et frais_pro, auxquelles le
// profil d'exercice 2026 (exercices.2026) renvoie par référence.
// =========================================================================

// Barème IR annuel 2026 : Art. 73 CGI (Loi de Finances 50-25)
$bareme2026 = [
    ['min' => 0, 'max' => 40000, 'taux' => 0.00, 'deduction' => 0],
    ['min' => 40001, 'max' => 60000, 'taux' => 0.10, 'deduction' => 4000],
    ['min' => 60001, 'max' => 80000, 'taux' => 0.20, 'deduction' => 10000],
    ['min' => 80001, 'max' => 100000, 'taux' => 0.30, 'deduction' => 18000],
    ['min' => 100001, 'max' => 180000, 'taux' => 0.34, 'deduction' => 22000],
    ['min' => 180001, 'max' => null, 'taux' => 0.37, 'deduction' => 27400],
];

// Frais professionnels 2026 : Art. 59 I-A CGI
// Plafond annuel unique : 35 000 MAD/an ≈ 2 916,67 MAD/mois (LF 2023, reconduit en 2026)
$fraisPro2026 = [
    'seuil_mensuel' => 6500,
    'commun' => [
        'bas' => ['taux' => 0.35, 'plafond' => 2916.67],
        'haut' => ['taux' => 0.25, 'plafond' => 2916.67],
    ],
    'journaliste' => ['taux' => 0.45, 'plafond' => 2916.67],
    'artiste' => ['taux' => 0.40, 'plafond' => 2916.67],
];

/*
 * Barème annuel construit depuis ses seuils et ses taux marginaux, la
 * déduction de chaque tranche étant dérivée par continuité au seuil précédent :
 *   déduction(i) = déduction(i-1) + max(i-1) x (taux(i) - taux(i-1)).
 * Chaque palier est [max inclus en MAD de RNI annuel, taux] ; le dernier a un
 * max null. Le minimum entier suit la convention historique : max précédent + 1.
 * Fonction anonyme locale : rien n'est déclaré globalement, le fichier peut être
 * rechargé (tests) et mis en cache (config:cache ne voit que le tableau produit).
 */
$baremeParContinuite = static function (array $paliers): array {
    $tranches = [];
    $min = 0;
    $deduction = 0.0;
    $maxPrecedent = null;
    $tauxPrecedent = null;

    foreach ($paliers as [$max, $taux]) {
        if ($tauxPrecedent !== null) {
            $deduction = round($deduction + $maxPrecedent * ($taux - $tauxPrecedent), 2);
        }

        $tranches[] = ['min' => $min, 'max' => $max, 'taux' => $taux, 'deduction' => $deduction];

        $min = $max === null ? null : $max + 1;
        $maxPrecedent = $max;
        $tauxPrecedent = $taux;
    }

    return $tranches;
};

// Source commune des exercices prospectifs : texte déposé, ni voté ni promulgué.
// Vérifié sur le PDF du projet (89 pages, sha256 ci-dessous) : art. 7-I (CGI
// art. 59-I, 73-I, 247-XXXXVII) et art. 7-IV (dates d'effet 5 et 6).
$sourcePlf2027 = [
    'texte' => 'Projet de loi de finances n° 60-26 pour l\'année budgétaire 2027',
    'version' => 'texte déposé',
    'date_publication' => '2026-10-23',
    'url' => 'https://www.finances.gov.ma/Publication/db/2027/corps-PLF2027-fr.pdf',
    'sha256' => '5794a1cca7423fd0107f8d7ff4fbfd3137aa67b8dc99a4adf57e0c045171dc4a',
];

return [

    'year' => 2026,
    'regulation' => 'Loi de Finances 50-25',

    // =========================================================================
    // Exercices fiscaux (issue #281)
    // `exercice_defaut` : repli déterministe sans exercice explicite (liens et
    // requêtes historiques). Il reste 2026 définitivement et ne dépend jamais
    // de la date du jour : un exercice ultérieur se demande explicitement.
    // `statut` : `en_vigueur` ou `projet`. Un projet n'est jamais présenté comme
    // en vigueur : `validation_legale` reste null tant que la loi publiée et ses
    // dates d'effet n'ont pas été vérifiées (#280, #15).
    // `date_effet_projetee` : date annoncée par le texte source, distincte de
    // toute validation légale.
    // `frais_pro` null : frais professionnels de l'exercice non paramétrés
    // (#282). Aucun calcul complet ne doit alors reprendre ceux de 2026.
    // =========================================================================
    'exercice_defaut' => 2026,

    'exercices' => [
        2026 => [
            'statut' => 'en_vigueur',
            'source' => ['texte' => 'Loi de Finances 50-25'],
            'reference_ir' => 'Art. 73-I CGI',
            'periode' => ['debut' => '2026-01-01', 'fin' => '2026-12-31'],
            'date_effet_projetee' => null,
            'validation_legale' => null,
            // Références vers les clés historiques archivées (ir.baremes,
            // frais_pro) : une seule source pour 2026, lue par le moteur comme
            // par la documentation et l'API.
            'ir_baremes' => 'ir.baremes',
            'frais_pro' => 'frais_pro',
        ],
        2027 => [
            'statut' => 'projet',
            'source' => $sourcePlf2027,
            'reference_ir' => 'Art. 247-XXXXVII-1 CGI (projet, art. 7-I du PLF n° 60-26)',
            'periode' => ['debut' => '2027-01-01', 'fin' => '2027-12-31'],
            'date_effet_projetee' => '2027-01-01',
            'validation_legale' => null,
            'ir_baremes' => $baremeParContinuite([
                [60000, 0.00], [80000, 0.10], [100000, 0.20], [180000, 0.34], [null, 0.37],
            ]),
            'frais_pro' => null,
        ],
        2028 => [
            'statut' => 'projet',
            'source' => $sourcePlf2027,
            'reference_ir' => 'Art. 247-XXXXVII-2 CGI (projet, art. 7-I du PLF n° 60-26)',
            'periode' => ['debut' => '2028-01-01', 'fin' => '2028-12-31'],
            'date_effet_projetee' => '2028-01-01',
            'validation_legale' => null,
            'ir_baremes' => $baremeParContinuite([
                [60000, 0.00], [100000, 0.10], [200000, 0.25], [null, 0.37],
            ]),
            'frais_pro' => null,
        ],
        2029 => [
            'statut' => 'projet',
            'source' => $sourcePlf2027,
            'reference_ir' => 'Art. 247-XXXXVII-3 CGI (projet, art. 7-I du PLF n° 60-26)',
            'periode' => ['debut' => '2029-01-01', 'fin' => '2029-12-31'],
            'date_effet_projetee' => '2029-01-01',
            'validation_legale' => null,
            'ir_baremes' => $baremeParContinuite([
                [60000, 0.00], [130000, 0.10], [200000, 0.20], [null, 0.35],
            ]),
            'frais_pro' => null,
        ],
        2030 => [
            'statut' => 'projet',
            'source' => $sourcePlf2027,
            'reference_ir' => 'Art. 247-XXXXVII-4 CGI (projet, art. 7-I du PLF n° 60-26)',
            'periode' => ['debut' => '2030-01-01', 'fin' => '2030-12-31'],
            'date_effet_projetee' => '2030-01-01',
            'validation_legale' => null,
            'ir_baremes' => $baremeParContinuite([
                [60000, 0.00], [150000, 0.10], [200000, 0.20], [null, 0.30],
            ]),
            'frais_pro' => null,
        ],
        // Barème cible : art. 73-I modifié, applicable à compter du 1er janvier
        // 2031 selon l'art. 7-IV-6 du projet.
        2031 => [
            'statut' => 'projet',
            'source' => $sourcePlf2027,
            'reference_ir' => 'Art. 73-I CGI modifié (projet, art. 7-I et 7-IV-6 du PLF n° 60-26)',
            'periode' => ['debut' => '2031-01-01', 'fin' => '2031-12-31'],
            'date_effet_projetee' => '2031-01-01',
            'validation_legale' => null,
            'ir_baremes' => $baremeParContinuite([
                [60000, 0.00], [150000, 0.10], [500000, 0.20], [null, 0.30],
            ]),
            'frais_pro' => null,
        ],
    ],

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
    // Taux librement choisi, 100 % deductible IR (part salarie)
    // Toujours en repartition partagee (salarie + employeur)
    // =========================================================================
    'cimr' => [
        'taux_min' => 0,
        'taux_max' => 0.50,
    ],

    // =========================================================================
    // Frais Professionnels — Art. 59 I-A CGI
    // Déduits de l'assiette IR (non remboursés au salarié)
    // =========================================================================
    // Valeurs 2026 définies en tête de fichier ($fraisPro2026), référencées par
    // exercices.2026.frais_pro.
    'frais_pro' => $fraisPro2026,

    // =========================================================================
    // Barème IR annuel 2026 — Art. 73 CGI (Loi de Finances 50-25)
    // Formule : IR annuel = RNI_annuel × taux − deduction
    // Méthode d'annualisation : RNI mensuel × 12 → IR annuel → ÷ 12
    // =========================================================================
    // Valeurs 2026 définies en tête de fichier ($bareme2026), référencées par
    // exercices.2026.ir_baremes. Clé conservée pour l'API, la documentation et
    // les tests historiques.
    'ir' => [
        'nb_mois' => 12,
        'baremes' => $bareme2026,
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
    // Déduction fiscale : plafonnée à 50% du SBI annuel
    // Peut être répartie entre salarié et employeur
    // =========================================================================
    'retraite_complementaire' => [
        'deduction_ir_max_pct' => 0.50,
        'article' => 'Art. 28-IV CGI',
    ],

];
