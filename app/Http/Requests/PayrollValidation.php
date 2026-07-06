<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PayrollValidation
{
    public static function commonRules(): array
    {
        return [
            'nb_annees_anciennete' => 'nullable|integer|min:0|max:50',
            'prime_bilan' => 'nullable|numeric|min:0',
            'prime_rendement' => 'nullable|numeric|min:0',
            'autres_primes' => 'nullable|numeric|min:0',
            'type_frais_pro' => 'required|in:commun,journaliste,artiste',
            'nb_enfants' => 'nullable|integer|min:0|max:20',
            'conjoint_charge' => 'nullable|boolean',
            'cimr_taux' => 'nullable|numeric|min:0',
            'cimr_taux_employeur' => 'nullable|numeric|min:0',
            'amo_taux_salarie_personnalise' => 'nullable|boolean',
            'amo_taux_salarie' => 'nullable|numeric|min:0|max:100',
            'retraite_complementaire_mensuel' => 'nullable|numeric|min:0',
            'rc_part_employeur' => 'nullable|numeric|min:0',
            'mutuelle_salarie' => 'nullable|numeric|min:0',
            'mutuelle_patronale' => 'nullable|numeric|min:0',
            'assurance_at_taux' => 'nullable|numeric|min:0|max:100',
            'assurance_rc_pro' => 'nullable|numeric|min:0',
            'retenues_exonerees_ir' => 'nullable|numeric|min:0',
            'retenues_imposees_ir' => 'nullable|numeric|min:0',
            'jours_travailles' => 'nullable|integer|min:1|max:31',
            'heures_sup' => 'nullable|array|max:10',
            'heures_sup.*.type' => 'required_with:heures_sup.*.nb_heures|in:semaine_diurne,semaine_nocturne,repos_diurne,repos_nocturne',
            'heures_sup.*.nb_heures' => 'nullable|numeric|min:0|max:744',
            'indemnites' => 'nullable|array|max:10',
            'indemnites.*.type' => ['required_with:indemnites.*.montant', 'distinct', Rule::in(array_keys(config('payroll.indemnites')))],
            'indemnites.*.montant' => 'nullable|numeric|min:0|max:1000000',
            'avantages_cnss' => 'nullable|numeric|min:0',
        ];
    }

    public static function brutVersNetRules(): array
    {
        return array_merge(
            ['salaire_base' => ['required', 'numeric', 'min:0', 'max:10000000']],
            static::commonRules(),
            ['autres_retenues' => 'nullable|numeric|min:0'],
        );
    }

    public static function netVersBrutRules(): array
    {
        return array_merge(
            ['net_cible' => ['required', 'numeric', 'min:0.01', 'max:10000000']],
            static::commonRules(),
            ['autres_retenues' => 'nullable|numeric|min:0'],
        );
    }

    public static function webRules(string $mode): array
    {
        return array_merge(
            [
                'mode' => ['nullable', Rule::in(['gross_to_net', 'net_to_gross'])],
                'salaire_base' => [
                    Rule::excludeIf($mode !== 'gross_to_net'),
                    Rule::requiredIf($mode === 'gross_to_net'),
                    'nullable', 'numeric', 'min:0', 'max:10000000',
                ],
                'net_cible' => [
                    Rule::excludeIf($mode !== 'net_to_gross'),
                    Rule::requiredIf($mode === 'net_to_gross'),
                    'nullable', 'numeric', 'min:0.01', 'max:10000000',
                ],
            ],
            static::commonRules(),
            [
                'rc_part_employeur_inconnu' => 'nullable|boolean',
                'cimr_taux_employeur_inconnu' => 'nullable|boolean',
                'mutuelle_patronale_inconnu' => 'nullable|boolean',
                'assurance_at_taux_inconnu' => 'nullable|boolean',
                'assurance_rc_pro_inconnu' => 'nullable|boolean',
            ],
        );
    }

    public static function webMessages(): array
    {
        return [
            'salaire_base.required' => __('ui.validation.base_required'),
            'salaire_base.min' => __('ui.validation.base_positive'),
            'net_cible.required' => __('ui.validation.net_target_required'),
            'net_cible.min' => __('ui.validation.net_target_positive'),
            'type_frais_pro.in' => __('ui.validation.category_invalid'),
            'indemnites.*.type.distinct' => __('ui.validation.allowance_distinct'),
        ];
    }

    public static function commonFields(): array
    {
        return [
            'nb_annees_anciennete',
            'prime_bilan', 'prime_rendement', 'autres_primes',
            'type_frais_pro', 'nb_enfants', 'conjoint_charge',
            'cimr_taux', 'cimr_taux_employeur',
            'amo_taux_salarie_personnalise', 'amo_taux_salarie',
            'retraite_complementaire_mensuel', 'rc_part_employeur',
            'mutuelle_salarie', 'mutuelle_patronale',
            'assurance_at_taux', 'assurance_rc_pro',
            'retenues_exonerees_ir', 'retenues_imposees_ir',
            'heures_sup', 'indemnites',
            'jours_travailles',
            'avantages_cnss',
        ];
    }

    public static function apiFields(string $mode): array
    {
        $primary = $mode === 'net_to_gross' ? 'net_cible' : 'salaire_base';

        return array_merge([$primary], static::commonFields(), ['autres_retenues']);
    }

    public static function webFields(): array
    {
        return array_merge(
            ['mode', 'salaire_base', 'net_cible'],
            static::commonFields(),
            [
                'cimr_taux_employeur_inconnu',
                'rc_part_employeur_inconnu',
                'mutuelle_patronale_inconnu',
                'assurance_at_taux_inconnu',
                'assurance_rc_pro_inconnu',
            ],
        );
    }
}
