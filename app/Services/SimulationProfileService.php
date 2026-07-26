<?php

namespace App\Services;

/**
 * Profils de simulation prêts à l'emploi (issue #51).
 *
 * Chaque profil est une simple fixture pédagogique : uniquement des valeurs
 * d'entrée du formulaire, jamais un taux, un plafond ou un barème. Les montants
 * qui dépendent de la réglementation (SMIG, plafond d'une indemnité) sont lus
 * depuis config/payroll.php pour éviter toute duplication des règles.
 *
 * Les libellés et descriptions vivent dans lang/*\/ui.php sous
 * ui.calculator.profiles.<slug>, afin de rester traduits en FR, EN, AR et ES.
 */
class SimulationProfileService
{
    /**
     * Ordre d'affichage des profils sur la page calculateur.
     */
    private const SLUGS = ['smig', 'standard', 'cadre', 'primes', 'cimr', 'journaliste'];

    /**
     * @return array<string, array{slug: string, icon: string, input: array<string, mixed>}>
     */
    public function all(): array
    {
        $profiles = [];

        foreach (self::SLUGS as $slug) {
            $profiles[$slug] = [
                'slug' => $slug,
                'icon' => $this->icon($slug),
                'input' => $this->input($slug),
            ];
        }

        return $profiles;
    }

    public function find(?string $slug): ?array
    {
        if ($slug === null || ! in_array($slug, self::SLUGS, true)) {
            return null;
        }

        return $this->all()[$slug];
    }

    public function exists(?string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    private function icon(string $slug): string
    {
        return [
            'smig' => 'bi-cash-coin',
            'standard' => 'bi-person',
            'cadre' => 'bi-person-badge',
            'primes' => 'bi-gift',
            'cimr' => 'bi-piggy-bank',
            'journaliste' => 'bi-newspaper',
        ][$slug];
    }

    /**
     * Première année de la n-ième tranche d'ancienneté configurée (1 = 2 ans et
     * plus, 2 = 5 ans et plus, etc.). Lit config/payroll.php pour ne pas figer
     * ici un seuil réglementaire.
     */
    private function debutDeTranche(int $rang): int
    {
        $tranches = array_values(config('payroll.anciennete.tranches'));

        return (int) $tranches[min($rang, count($tranches)) - 1]['min_annees'];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(string $slug): array
    {
        $common = ['mode' => 'gross_to_net', 'type_frais_pro' => 'commun'];

        return match ($slug) {
            // Salaire minimum légal : lu depuis la configuration réglementaire.
            'smig' => $common + [
                'salaire_base' => (float) config('payroll.smig.mensuel'),
            ],

            // Les années d'ancienneté correspondent au début de tranche : c'est la
            // seule granularité proposée par le sélecteur du formulaire, donc la
            // seule valeur qu'il peut réafficher telle quelle.
            'standard' => $common + [
                'salaire_base' => 6000.0,
                'nb_annees_anciennete' => $this->debutDeTranche(1),
            ],

            'cadre' => $common + [
                'salaire_base' => 15000.0,
                'nb_annees_anciennete' => $this->debutDeTranche(2),
                'nb_enfants' => 2,
                'conjoint_charge' => true,
            ],

            // Indemnité de transport calée sur son plafond d'exonération configuré,
            // pour montrer une indemnité entièrement exonérée.
            'primes' => $common + [
                'salaire_base' => 9000.0,
                'autres_primes' => 1500.0,
                'indemnites' => [
                    ['type' => 'transport', 'montant' => (float) config('payroll.indemnites.transport.montant')],
                ],
                'heures_sup' => [
                    ['type' => 'semaine_diurne', 'nb_heures' => 10],
                ],
            ],

            'cimr' => $common + [
                'salaire_base' => 10000.0,
                'cimr_taux' => 6,
                'cimr_taux_employeur' => 3,
            ],

            'journaliste' => ['mode' => 'gross_to_net', 'type_frais_pro' => 'journaliste'] + [
                'salaire_base' => 12000.0,
            ],
        };
    }
}
