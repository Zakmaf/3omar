<?php

/*
 * رسائل قواعد التحقق المستعملة في App\Http\Requests\PayrollValidation (#187).
 * لا نسب ولا سقوف هنا: تُدرج الحدود عبر :min و :max.
 */

return [
    'array' => 'يجب أن يكون حقل :attribute قائمة.',
    'boolean' => 'يجب أن تكون قيمة حقل :attribute صحيحة أو خاطئة.',
    'distinct' => 'يحتوي حقل :attribute على قيمة مكررة.',
    'in' => 'قيمة حقل :attribute غير صالحة.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عناصر.',
        'numeric' => 'يجب ألا تتجاوز قيمة حقل :attribute :max.',
        'string' => 'يجب ألا يتجاوز طول حقل :attribute :max حروف.',
    ],
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عناصر على الأقل.',
        'numeric' => 'يجب ألا تقل قيمة حقل :attribute عن :min.',
        'string' => 'يجب ألا يقل طول حقل :attribute عن :min حروف.',
    ],
    'numeric' => 'يجب أن يكون حقل :attribute رقمًا.',
    'required' => 'حقل :attribute إلزامي.',
    'required_with' => 'حقل :attribute إلزامي عند إدخال :values.',

    'custom' => [
        'heures_sup.*.type' => [
            'required_with' => 'حقل :attribute إلزامي عند إدخال عدد الساعات في السطر نفسه.',
        ],
        'indemnites.*.type' => [
            'required_with' => 'حقل :attribute إلزامي عند إدخال مبلغ في السطر نفسه.',
        ],
    ],

    'attributes' => [
        'mode' => 'وضع الحساب',
        'salaire_base' => 'الأجر الأساسي',
        'net_cible' => 'الصافي المستهدف للأداء',
        'nb_annees_anciennete' => 'سنوات الأقدمية',
        'prime_bilan' => 'منحة الحصيلة',
        'prime_rendement' => 'منحة المردودية',
        'autres_primes' => 'المنح الخاضعة للضريبة',
        'type_frais_pro' => 'الفئة المهنية',
        'nb_enfants' => 'الأطفال المعالون',
        'conjoint_charge' => 'الزوج/الزوجة المُعال(ة)',
        'cimr_taux' => 'نسبة CIMR للأجير',
        'cimr_taux_employeur' => 'نسبة CIMR للمشغل',
        'amo_taux_salarie_personnalise' => 'تخصيص نسبة AMO الخاصة بالأجير',
        'amo_taux_salarie' => 'نسبة AMO مخصّصة للأجير',
        'retraite_complementaire_mensuel' => 'التقاعد التكميلي (حصة الأجير)',
        'rc_part_employeur' => 'التقاعد التكميلي (حصة المشغل)',
        'mutuelle_salarie' => 'التعاضدية (حصة الأجير)',
        'mutuelle_patronale' => 'التعاضدية (حصة المشغل)',
        'assurance_at_taux' => 'نسبة التأمين عن حوادث الشغل',
        'assurance_rc_pro' => 'تأمين المسؤولية المدنية المهنية',
        'retenues_exonerees_ir' => 'اقتطاعات معفاة من الضريبة على الدخل',
        'retenues_imposees_ir' => 'اقتطاعات خاضعة للضريبة على الدخل',
        'jours_travailles' => 'أيام العمل في الشهر',
        'heures_sup' => 'الساعات الإضافية',
        'heures_sup.*.type' => 'نوع الساعات الإضافية (السطر :position)',
        'heures_sup.*.nb_heures' => 'عدد الساعات الإضافية (السطر :position)',
        'indemnites' => 'التعويضات',
        'indemnites.*.type' => 'نوع التعويض (السطر :position)',
        'indemnites.*.montant' => 'مبلغ التعويض (السطر :position)',
        'avantages_cnss' => 'امتيازات CNSS المعفاة',
        'autres_retenues' => 'اقتطاعات أخرى',
        'cimr_taux_employeur_inconnu' => 'نسبة CIMR للمشغل غير معروفة',
        'rc_part_employeur_inconnu' => 'حصة المشغل في التقاعد التكميلي غير معروفة',
        'mutuelle_patronale_inconnu' => 'حصة المشغل في التعاضدية غير معروفة',
        'assurance_at_taux_inconnu' => 'نسبة التأمين عن حوادث الشغل غير معروفة',
        'assurance_rc_pro_inconnu' => 'تأمين المسؤولية المدنية المهنية غير معروف',
    ],
];
