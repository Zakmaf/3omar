<?php

/*
 * Messages for the validation rules used by App\Http\Requests\PayrollValidation (#187).
 * The API also answers in English but names fields by their JSON key (see the API form requests).
 * No rate or ceiling here: bounds are injected through :min and :max.
 */

return [
    'array' => 'The :attribute field must be an array.',
    'boolean' => 'The :attribute field must be true or false.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'in' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute field must be an integer.',
    'max' => [
        'array' => 'The :attribute field must not have more than :max items.',
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'array' => 'The :attribute field must have at least :min items.',
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'numeric' => 'The :attribute field must be a number.',
    'required' => 'The :attribute field is required.',
    'required_with' => 'The :attribute field is required when :values is present.',

    'custom' => [
        'heures_sup.*.type' => [
            'required_with' => 'The :attribute field is required when hours are entered on the same line.',
        ],
        'indemnites.*.type' => [
            'required_with' => 'The :attribute field is required when an amount is entered on the same line.',
        ],
    ],

    'attributes' => [
        'mode' => 'calculation mode',
        'salaire_base' => 'base salary',
        'net_cible' => 'target net pay',
        'nb_annees_anciennete' => 'years of seniority',
        'prime_bilan' => 'year-end bonus',
        'prime_rendement' => 'performance bonus',
        'autres_primes' => 'taxable bonuses',
        'type_frais_pro' => 'professional category',
        'nb_enfants' => 'dependent children',
        'conjoint_charge' => 'dependent spouse',
        'cimr_taux' => 'CIMR employee rate',
        'cimr_taux_employeur' => 'CIMR employer rate',
        'amo_taux_salarie_personnalise' => 'custom employee AMO rate option',
        'amo_taux_salarie' => 'custom employee AMO rate',
        'retraite_complementaire_mensuel' => 'supplementary pension (employee share)',
        'rc_part_employeur' => 'supplementary pension (employer share)',
        'mutuelle_salarie' => 'health insurance (employee share)',
        'mutuelle_patronale' => 'health insurance (employer share)',
        'assurance_at_taux' => 'work accident insurance rate',
        'assurance_rc_pro' => 'professional liability insurance',
        'retenues_exonerees_ir' => 'income-tax-exempt deductions',
        'retenues_imposees_ir' => 'income-tax-charged deductions',
        'jours_travailles' => 'working days in the month',
        'heures_sup' => 'overtime',
        'heures_sup.*.type' => 'overtime type (line :position)',
        'heures_sup.*.nb_heures' => 'overtime hours (line :position)',
        'indemnites' => 'allowances',
        'indemnites.*.type' => 'allowance type (line :position)',
        'indemnites.*.montant' => 'allowance amount (line :position)',
        'avantages_cnss' => 'CNSS-exempt benefits',
        'autres_retenues' => 'other deductions',
        'cimr_taux_employeur_inconnu' => 'unknown CIMR employer rate',
        'rc_part_employeur_inconnu' => 'unknown supplementary pension employer share',
        'mutuelle_patronale_inconnu' => 'unknown health insurance employer share',
        'assurance_at_taux_inconnu' => 'unknown work accident insurance rate',
        'assurance_rc_pro_inconnu' => 'unknown professional liability insurance',
    ],
];
