<?php

namespace App\Services;

/**
 * Résolution des exercices fiscaux déclarés dans config('payroll.exercices')
 * (issue #281). Lecture seule : la configuration n'est jamais modifiée.
 *
 * Partagé par la validation (API, formulaire, liens partagés) et par le
 * moteur, pour qu'un exercice soit accepté ou refusé selon la même règle.
 *
 * - Sans exercice (absent, null ou vide), repli sur
 *   config('payroll.exercice_defaut'), fixé à 2026 pour les liens et requêtes
 *   historiques. Les nouvelles interfaces transmettent l'exercice explicitement.
 * - Un exercice n'est calculable que si son profil déclare un barème IR et des
 *   frais professionnels. Un profil prospectif sans frais professionnels
 *   (#282) est refusé, jamais complété par les valeurs 2026.
 * - Un paramètre de profil donné sous forme de chaîne désigne une clé
 *   historique de config('payroll') (archives 2026), lue telle quelle.
 */
class ExercicesFiscaux
{
    public function defaut(): int
    {
        return (int) config('payroll.exercice_defaut');
    }

    /**
     * Exercice demandé, ou le repli historique sans exercice. Une valeur non
     * entière est rendue telle quelle à la validation, qui la refuse.
     */
    public function resoudre(mixed $exercice): ?int
    {
        // Une chaîne entièrement blanche vaut absence, comme pour TrimStrings et
        // la validation Laravel, qui n'applique pas ses règles à cette valeur.
        if ($exercice === null || (is_string($exercice) && trim($exercice) === '')) {
            return $this->defaut();
        }

        if (is_int($exercice)) {
            return $exercice;
        }

        if (is_string($exercice) && preg_match('/^\d{4}$/', $exercice) === 1) {
            return (int) $exercice;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function declares(): array
    {
        return array_map('intval', array_keys(config('payroll.exercices', [])));
    }

    public function existe(int $exercice): bool
    {
        return is_array(config("payroll.exercices.{$exercice}"));
    }

    public function calculable(int $exercice): bool
    {
        return $this->existe($exercice)
            && is_array($this->parametre($exercice, 'ir_baremes'))
            && is_array($this->parametre($exercice, 'frais_pro'));
    }

    /**
     * Paramètre d'un profil existant, clé historique résolue. Null si le
     * profil ne le déclare pas.
     */
    public function parametre(int $exercice, string $cle): mixed
    {
        if (! $this->existe($exercice)) {
            throw new \InvalidArgumentException(__('ui.validation.exercise_unknown', ['exercice' => $exercice]));
        }

        $valeur = config("payroll.exercices.{$exercice}.{$cle}");

        return is_string($valeur) ? config("payroll.{$valeur}") : $valeur;
    }

    /**
     * Message d'erreur traduit si l'exercice demandé ne peut pas servir à un
     * calcul complet, null s'il est calculable.
     */
    public function erreur(mixed $exercice): ?string
    {
        $annee = $this->resoudre($exercice);

        if ($annee === null || ! $this->existe($annee)) {
            return __('ui.validation.exercise_unknown', ['exercice' => is_scalar($exercice) ? (string) $exercice : '']);
        }

        if (! $this->calculable($annee)) {
            return __('ui.validation.exercise_not_calculable', ['exercice' => $annee]);
        }

        return null;
    }

    /**
     * Paramètres d'un exercice calculable, ou exception explicite avant tout
     * calcul.
     *
     * @return array{exercice: int, ir_baremes: array, frais_pro: array}
     */
    public function profilCalculable(mixed $exercice): array
    {
        $erreur = $this->erreur($exercice);

        if ($erreur !== null) {
            throw new \InvalidArgumentException($erreur);
        }

        $annee = $this->resoudre($exercice);

        return [
            'exercice' => $annee,
            'ir_baremes' => $this->parametre($annee, 'ir_baremes'),
            'frais_pro' => $this->parametre($annee, 'frais_pro'),
        ];
    }
}
