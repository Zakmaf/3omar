<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SimulerNetVersBrutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'net_cible' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'nb_annees_anciennete' => 'nullable|integer|min:0|max:50',
            'prime_bilan' => 'nullable|numeric|min:0',
            'prime_rendement' => 'nullable|numeric|min:0',
            'autres_primes' => 'nullable|numeric|min:0',
            'type_frais_pro' => 'required|in:commun,journaliste,artiste',
            'nb_enfants' => 'nullable|integer|min:0|max:20',
            'conjoint_charge' => 'nullable|boolean',
            'cimr_taux' => 'nullable|numeric|min:0',
            'cimr_taux_employeur' => 'nullable|numeric|min:0',
            'retraite_complementaire_mensuel' => 'nullable|numeric|min:0',
            'rc_part_employeur' => 'nullable|numeric|min:0',
            'mutuelle_salarie' => 'nullable|numeric|min:0',
            'mutuelle_patronale' => 'nullable|numeric|min:0',
            'autres_retenues' => 'nullable|numeric|min:0',
            'jours_travailles' => 'nullable|integer|min:1|max:31',
            'heures_sup' => 'nullable|array|max:10',
            'heures_sup.*.type' => 'required_with:heures_sup.*.nb_heures|in:semaine_diurne,semaine_nocturne,repos_diurne,repos_nocturne',
            'heures_sup.*.nb_heures' => 'nullable|numeric|min:0|max:744',
            'indemnites' => 'nullable|array|max:10',
            'indemnites.*.type' => ['required_with:indemnites.*.montant', 'distinct', Rule::in(array_keys(config('payroll.indemnites')))],
            'indemnites.*.montant' => 'nullable|numeric|min:0|max:1000000',
            'prime_scolarite' => 'nullable|numeric|min:0',
            'prime_aid' => 'nullable|numeric|min:0',
            'autres_avantages_cnss' => 'nullable|numeric|min:0',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $firstMessage = $errors->first();

        throw new HttpResponseException(response()->json([
            'type' => 'about:blank',
            'title' => 'Unprocessable Content',
            'status' => 422,
            'detail' => $firstMessage,
            'errors' => $errors->toArray(),
        ], 422));
    }
}
