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
 *   frais professionnels complets (fraisProComplets). Un profil prospectif
 *   sans frais professionnels (2028 à 2031) est refusé, jamais complété par
 *   les valeurs 2026.
 * - Avec des frais professionnels à taux unique (projet 2027, #282), seules
 *   les catégories rattachées par le profil sont calculables.
 * - Un paramètre de profil donné sous forme de chaîne désigne une clé
 *   historique de config('payroll') (archives 2026), lue telle quelle.
 */
class ExercicesFiscaux
{
    /** Métadonnées de provenance exposées dans chaque résultat (reference_fiscale). */
    private const METADONNEES = ['statut', 'source', 'reference_ir', 'periode', 'date_effet_projetee', 'validation_legale'];

    /** Catégories de frais professionnels saisissables (champ type_frais_pro). */
    public const CATEGORIES = ['commun', 'journaliste', 'artiste'];

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
            && $this->fraisProComplets($this->parametre($exercice, 'frais_pro'));
    }

    /**
     * Frais professionnels exploitables par le moteur, sous l'une des deux
     * formes déclarées (#282) :
     * - par seuil (2026, art. 59-I A et B en vigueur) : seuil_mensuel, taux
     *   commun bas et haut, catégories journaliste et artiste ;
     * - à taux unique (projet 2027, art. 59-I-A modifié) : taux, plafond
     *   annuel canonique et liste explicite des catégories rattachées.
     * Un profil partiel n'est jamais calculable : il n'est pas complété.
     */
    public function fraisProComplets(mixed $fraisPro): bool
    {
        if (! is_array($fraisPro)) {
            return false;
        }

        if ($this->fraisProTauxUnique($fraisPro)) {
            return is_numeric($fraisPro['taux'] ?? null)
                && is_numeric($fraisPro['plafond_annuel'])
                && is_array($fraisPro['categories'] ?? null)
                && $fraisPro['categories'] !== [];
        }

        foreach (['commun.bas', 'commun.haut', 'journaliste', 'artiste'] as $cle) {
            if (! is_numeric(data_get($fraisPro, "{$cle}.taux")) || ! is_numeric(data_get($fraisPro, "{$cle}.plafond"))) {
                return false;
            }
        }

        return is_numeric($fraisPro['seuil_mensuel'] ?? null);
    }

    /** Forme à taux unique : identifiée par son plafond annuel canonique. */
    public function fraisProTauxUnique(array $fraisPro): bool
    {
        return array_key_exists('plafond_annuel', $fraisPro);
    }

    /**
     * Catégories de frais professionnels calculables pour un exercice
     * calculable. Forme par seuil : les trois catégories qu'elle paramètre.
     * Forme à taux unique : uniquement celles que le profil rattache
     * explicitement à son régime.
     *
     * @return list<string>
     */
    public function categoriesFraisPro(int $exercice): array
    {
        $fraisPro = $this->parametre($exercice, 'frais_pro');

        if (! $this->fraisProComplets($fraisPro)) {
            return [];
        }

        if ($this->fraisProTauxUnique($fraisPro)) {
            return array_values(array_map('strval', $fraisPro['categories']));
        }

        return self::CATEGORIES;
    }

    /**
     * Message traduit si la catégorie demandée n'est pas calculable dans
     * l'exercice demandé, null sinon. Un exercice lui-même invalide ou non
     * calculable n'est pas jugé ici : son erreur est portée par le champ
     * exercice (erreur()). Une catégorie inconnue de l'application est
     * refusée par la règle `in` du champ.
     */
    public function erreurCategorie(mixed $exercice, mixed $categorie): ?string
    {
        if ($this->erreur($exercice) !== null || ! in_array($categorie, self::CATEGORIES, true)) {
            return null;
        }

        $annee = $this->resoudre($exercice);

        if (in_array($categorie, $this->categoriesFraisPro($annee), true)) {
            return null;
        }

        return $this->messageCategorie($annee);
    }

    public function messageCategorie(int $exercice): string
    {
        return __('ui.validation.category_not_supported', ['exercice' => $exercice]);
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
     * Métadonnées juridiques d'un profil existant, lues brutes : contrairement
     * à parametre(), aucune chaîne n'est interprétée comme référence de clé.
     * Ni barème ni frais professionnels, et aucun lien avec la version
     * applicative ni avec la date du jour.
     *
     * @return array{statut: mixed, source: mixed, reference_ir: mixed, periode: mixed, date_effet_projetee: mixed, validation_legale: mixed}
     */
    public function referenceFiscale(int $exercice): array
    {
        if (! $this->existe($exercice)) {
            throw new \InvalidArgumentException(__('ui.validation.exercise_unknown', ['exercice' => $exercice]));
        }

        $profil = config("payroll.exercices.{$exercice}");
        $reference = [];

        foreach (self::METADONNEES as $cle) {
            $reference[$cle] = $profil[$cle] ?? null;
        }

        return $reference;
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
     * @return array{exercice: int, ir_baremes: array, frais_pro: array, reference_fiscale: array}
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
            'reference_fiscale' => $this->referenceFiscale($annee),
        ];
    }
}
