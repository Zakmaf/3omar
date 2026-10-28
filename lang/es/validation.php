<?php

/*
 * Mensajes de las reglas de validación usadas por App\Http\Requests\PayrollValidation (#187).
 * Ninguna tasa ni tope aquí: los límites se inyectan mediante :min y :max.
 */

return [
    'array' => 'El campo :attribute debe ser una lista.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'distinct' => 'El campo :attribute contiene un valor duplicado.',
    'in' => 'El valor del campo :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'array' => 'El campo :attribute no debe contener más de :max elementos.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe superar :max caracteres.',
    ],
    'min' => [
        'array' => 'El campo :attribute debe contener al menos :min elementos.',
        'numeric' => 'El campo :attribute debe ser como mínimo :min.',
        'string' => 'El campo :attribute debe contener al menos :min caracteres.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',

    'custom' => [
        'heures_sup.*.type' => [
            'required_with' => 'El campo :attribute es obligatorio cuando se indican horas en la misma línea.',
        ],
        'indemnites.*.type' => [
            'required_with' => 'El campo :attribute es obligatorio cuando se indica un importe en la misma línea.',
        ],
    ],

    'attributes' => [
        'mode' => 'modo de cálculo',
        'salaire_base' => 'salario base',
        'net_cible' => 'neto a pagar objetivo',
        'nb_annees_anciennete' => 'años de antigüedad',
        'prime_bilan' => 'prima de balance',
        'prime_rendement' => 'prima de rendimiento',
        'autres_primes' => 'primas imponibles',
        'type_frais_pro' => 'categoría profesional',
        'nb_enfants' => 'hijos a cargo',
        'conjoint_charge' => 'cónyuge a cargo',
        'cimr_taux' => 'tasa CIMR empleado',
        'cimr_taux_employeur' => 'tasa CIMR empleador',
        'amo_taux_salarie_personnalise' => 'personalización de la tasa AMO del empleado',
        'amo_taux_salarie' => 'tasa AMO del empleado personalizada',
        'retraite_complementaire_mensuel' => 'jubilación complementaria (parte empleado)',
        'rc_part_employeur' => 'jubilación complementaria (parte empleador)',
        'mutuelle_salarie' => 'seguro de salud (parte empleado)',
        'mutuelle_patronale' => 'seguro de salud (parte empleador)',
        'assurance_at_taux' => 'tasa del seguro de accidentes de trabajo',
        'assurance_rc_pro' => 'seguro de responsabilidad civil profesional',
        'retenues_exonerees_ir' => 'retenciones exentas del IR',
        'retenues_imposees_ir' => 'retenciones sujetas al IR',
        'jours_travailles' => 'días trabajados en el mes',
        'heures_sup' => 'horas extra',
        'heures_sup.*.type' => 'tipo de horas extra (línea :position)',
        'heures_sup.*.nb_heures' => 'número de horas extra (línea :position)',
        'indemnites' => 'indemnizaciones',
        'indemnites.*.type' => 'tipo de indemnización (línea :position)',
        'indemnites.*.montant' => 'importe de la indemnización (línea :position)',
        'avantages_cnss' => 'ventajas CNSS exentas',
        'autres_retenues' => 'otras retenciones',
        'cimr_taux_employeur_inconnu' => 'tasa CIMR empleador desconocida',
        'rc_part_employeur_inconnu' => 'parte empleador de jubilación complementaria desconocida',
        'mutuelle_patronale_inconnu' => 'parte empleador del seguro de salud desconocida',
        'assurance_at_taux_inconnu' => 'tasa del seguro de accidentes de trabajo desconocida',
        'assurance_rc_pro_inconnu' => 'seguro de responsabilidad civil profesional desconocido',
    ],
];
