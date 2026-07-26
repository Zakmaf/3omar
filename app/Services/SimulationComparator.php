<?php

namespace App\Services;

/**
 * Compare deux résultats de simulation et produit les écarts affichés par la
 * vue de comparaison (issue #47).
 *
 * Ce service ne calcule aucune paie : il ne lit que des montants déjà produits
 * par PayrollCalculatorService, afin qu'aucune règle réglementaire ne soit
 * dupliquée ici.
 */
class SimulationComparator
{
    /**
     * Indicateurs comparés, dans l'ordre d'affichage.
     *
     * - `key`     : clé du tableau de résultat
     * - `label`   : clé de traduction sous ui.comparison.metrics
     * - `sens`    : lecture d'une hausse pour l'utilisateur
     *               'gain'   une hausse est favorable au salarié (net)
     *               'charge' une hausse est un coût (cotisations, IR, coût employeur)
     *               'neutre' pas de lecture favorable/défavorable (assiettes)
     * - `phare`   : indicateur mis en avant en tête de comparaison
     */
    private const METRICS = [
        ['key' => 'salaire_net', 'label' => 'net', 'sens' => 'gain', 'phare' => true],
        ['key' => 'cout_total_employeur', 'label' => 'employer_cost', 'sens' => 'charge', 'phare' => true],
        ['key' => 'salaire_brut_total', 'label' => 'gross_total', 'sens' => 'neutre', 'phare' => false],
        ['key' => 'sbi', 'label' => 'taxable_gross', 'sens' => 'neutre', 'phare' => false],
        ['key' => 'cotisation_cnss', 'label' => 'cnss', 'sens' => 'charge', 'phare' => false],
        ['key' => 'cotisation_amo', 'label' => 'amo', 'sens' => 'charge', 'phare' => false],
        ['key' => 'cotisation_cimr', 'label' => 'cimr', 'sens' => 'charge', 'phare' => false],
        ['key' => 'total_sociales', 'label' => 'social_total', 'sens' => 'charge', 'phare' => false],
        ['key' => 'ir_net', 'label' => 'ir', 'sens' => 'charge', 'phare' => false],
        ['key' => 'total_retenues', 'label' => 'deductions', 'sens' => 'charge', 'phare' => false],
        ['key' => 'total_patronal', 'label' => 'employer_contributions', 'sens' => 'charge', 'phare' => false],
    ];

    /**
     * @return list<array{key: string, label: string, sens: string, phare: bool, a: float, b: float, ecart: float, ecart_pct: float|null}>
     */
    public function metrics(array $a, array $b): array
    {
        $metrics = [];

        foreach (self::METRICS as $metric) {
            $valeurA = (float) ($a[$metric['key']] ?? 0);
            $valeurB = (float) ($b[$metric['key']] ?? 0);
            $ecart = round($valeurB - $valeurA, 2);

            $metrics[] = $metric + [
                'a' => $valeurA,
                'b' => $valeurB,
                'ecart' => $ecart,
                // Un écart relatif n'a pas de sens quand le scénario A vaut zéro.
                'ecart_pct' => $valeurA != 0.0 ? round($ecart / abs($valeurA) * 100, 2) : null,
            ];
        }

        return $metrics;
    }

    /**
     * Écarts mis en avant en tête de page.
     *
     * @return list<array<string, mixed>>
     */
    public function highlights(array $a, array $b): array
    {
        return array_values(array_filter(
            $this->metrics($a, $b),
            fn (array $metric) => $metric['phare'],
        ));
    }

    /**
     * Liste des entrées qui diffèrent entre les deux scénarios, pour expliquer
     * l'origine des écarts sans obliger l'utilisateur à relire les deux formulaires.
     *
     * @return list<array{field: string, a: mixed, b: mixed}>
     */
    public function inputDifferences(array $inputA, array $inputB): array
    {
        $champs = array_unique([...array_keys($inputA), ...array_keys($inputB)]);
        sort($champs);

        $differences = [];

        foreach ($champs as $champ) {
            $a = $this->normalize($inputA[$champ] ?? null);
            $b = $this->normalize($inputB[$champ] ?? null);

            if ($a !== $b) {
                $differences[] = ['field' => $champ, 'a' => $a, 'b' => $b];
            }
        }

        return $differences;
    }

    /**
     * Ramène les entrées à une forme comparable : « vide », « 0 » et « absent »
     * décrivent la même situation dans le formulaire et ne sont pas des écarts.
     */
    private function normalize(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return json_encode(array_values($value));
        }

        if (is_bool($value)) {
            return $value ? '1' : null;
        }

        if (is_numeric($value)) {
            return (float) $value === 0.0 ? null : (string) round((float) $value, 4);
        }

        return (string) $value;
    }
}
