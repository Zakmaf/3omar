<?php

/*
 * Messages des règles de validation utilisées par App\Http\Requests\PayrollValidation (#187).
 * Aucun taux ni plafond ici : les bornes sont injectées par :min et :max.
 */

return [
    'array' => 'Le champ :attribute doit être une liste.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'in' => 'La valeur du champ :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'numeric' => 'Le champ :attribute ne peut pas dépasser :max.',
        'string' => 'Le champ :attribute ne peut pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigné.',

    'custom' => [
        'heures_sup.*.type' => [
            'required_with' => 'Le champ :attribute est obligatoire quand des heures sont saisies sur la même ligne.',
        ],
        'indemnites.*.type' => [
            'required_with' => 'Le champ :attribute est obligatoire quand un montant est saisi sur la même ligne.',
        ],
    ],

    'attributes' => [
        'mode' => 'mode de calcul',
        'salaire_base' => 'salaire de base',
        'net_cible' => 'net à payer cible',
        'nb_annees_anciennete' => "années d'ancienneté",
        'prime_bilan' => 'prime de bilan',
        'prime_rendement' => 'prime de rendement',
        'autres_primes' => 'primes imposables',
        'type_frais_pro' => 'catégorie professionnelle',
        'nb_enfants' => 'enfants à charge',
        'conjoint_charge' => 'conjoint à charge',
        'cimr_taux' => 'taux CIMR salarié',
        'cimr_taux_employeur' => 'taux CIMR employeur',
        'amo_taux_salarie_personnalise' => 'personnalisation du taux AMO salarié',
        'amo_taux_salarie' => 'taux AMO salarié personnalisé',
        'retraite_complementaire_mensuel' => 'retraite complémentaire (part salarié)',
        'rc_part_employeur' => 'retraite complémentaire (part employeur)',
        'mutuelle_salarie' => 'mutuelle (part salarié)',
        'mutuelle_patronale' => 'mutuelle (part employeur)',
        'assurance_at_taux' => "taux d'assurance accident du travail",
        'assurance_rc_pro' => 'assurance RC professionnelle',
        'retenues_exonerees_ir' => "retenues exonérées d'IR",
        'retenues_imposees_ir' => "retenues imposées à l'IR",
        'jours_travailles' => 'jours travaillés dans le mois',
        'heures_sup' => 'heures supplémentaires',
        'heures_sup.*.type' => 'type des heures supplémentaires (ligne :position)',
        'heures_sup.*.nb_heures' => "nombre d'heures supplémentaires (ligne :position)",
        'indemnites' => 'indemnités',
        'indemnites.*.type' => "type d'indemnité (ligne :position)",
        'indemnites.*.montant' => "montant de l'indemnité (ligne :position)",
        'avantages_cnss' => 'avantages CNSS exonérés',
        'autres_retenues' => 'autres retenues',
        'cimr_taux_employeur_inconnu' => 'taux CIMR employeur inconnu',
        'rc_part_employeur_inconnu' => 'part employeur de retraite complémentaire inconnue',
        'mutuelle_patronale_inconnu' => 'part employeur de mutuelle inconnue',
        'assurance_at_taux_inconnu' => "taux d'assurance accident du travail inconnu",
        'assurance_rc_pro_inconnu' => 'assurance RC professionnelle inconnue',
    ],
];
