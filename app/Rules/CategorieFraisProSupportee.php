<?php

namespace App\Rules;

use App\Services\ExercicesFiscaux;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La catégorie de frais professionnels doit être calculable dans l'exercice
 * demandé (issue #282). Même règle que le moteur (ExercicesFiscaux), appliquée
 * à l'API, au formulaire et aux liens partagés : une catégorie que le profil ne
 * rattache pas à son régime est refusée sur le champ type_frais_pro, jamais
 * calculée avec un autre régime.
 */
class CategorieFraisProSupportee implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $erreur = app(ExercicesFiscaux::class)->erreurCategorie($this->data['exercice'] ?? null, $value);

        if ($erreur !== null) {
            $fail($erreur);
        }
    }
}
