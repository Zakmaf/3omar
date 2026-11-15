<?php

namespace App\Services;

/**
 * Gain de salaire net au 1er janvier 2027 face à 2026 (issue #283).
 *
 * Une seule saisie, calculée à brut et paramètres constants sous l'exercice
 * 2026 (règles en vigueur) et sous l'exercice 2027 (projet de loi de finances
 * n° 60-26, statut projet). Ce service ne contient aucune formule de paie :
 * - les trois résultats (2026, intermédiaire, 2027) viennent de
 *   PayrollCalculatorService::decomposerChangementExercice() ;
 * - les écarts viennent de SimulationComparator::metrics() ;
 * - la décomposition est la différence de nets déjà produits par le moteur,
 *   dans l'ordre explicite barème puis frais professionnels.
 *
 * La projection annuelle est indicative : douze mois identiques
 * (config('payroll.ir.nb_mois')), sans prime ponctuelle ni année réelle (#44).
 */
class GainFiscal2027
{
    public const EXERCICE_REFERENCE = 2026;

    public const EXERCICE_PROJETE = 2027;

    /** Ordre de la décomposition : chaque étape change un seul paramètre. */
    private const ORDRE = ['bareme', 'frais_pro'];

    public function __construct(
        private PayrollCalculatorService $moteur,
        private SimulationComparator $comparateur,
    ) {}

    /**
     * @return array{
     *     mode: string,
     *     exercices: array{reference: int, projete: int},
     *     reference: array,
     *     projete: array,
     *     metrics: list<array<string, mixed>>,
     *     gain: array{net_mensuel: float, net_pct: float|null, nb_mois: int, net_annuel_indicatif: float},
     *     decomposition: array{ordre: list<string>, net_intermediaire: float, bareme: float, frais_pro: float}
     * }
     */
    public function comparer(array $saisie): array
    {
        $mode = $saisie['mode'] ?? 'gross_to_net';

        // Seul le brut vers net est couvert à ce stade. Le net cible (brut
        // résolu en 2026 puis figé pour les deux exercices) viendra dans une
        // tranche dédiée : il est refusé explicitement, jamais calculé comme
        // un brut.
        if ($mode !== 'gross_to_net') {
            throw new \InvalidArgumentException('Mode de calcul non pris en charge par la comparaison 2026 / 2027.');
        }

        $calculs = $this->moteur->decomposerChangementExercice($saisie, self::EXERCICE_REFERENCE, self::EXERCICE_PROJETE);
        $reference = $calculs['reference'];
        $projete = $calculs['projete'];
        $netIntermediaire = $calculs['intermediaire']['salaire_net'];

        $metrics = $this->comparateur->metrics($reference, $projete);
        $net = $this->metric($metrics, 'salaire_net');
        $nbMois = (int) config('payroll.ir.nb_mois');

        return [
            'mode' => $mode,
            'exercices' => ['reference' => self::EXERCICE_REFERENCE, 'projete' => self::EXERCICE_PROJETE],
            'reference' => $reference,
            'projete' => $projete,
            'metrics' => $metrics,
            'gain' => [
                'net_mensuel' => $net['ecart'],
                'net_pct' => $net['ecart_pct'],
                'nb_mois' => $nbMois,
                'net_annuel_indicatif' => round($net['ecart'] * $nbMois, 2),
            ],
            'decomposition' => [
                'ordre' => self::ORDRE,
                'net_intermediaire' => $netIntermediaire,
                'bareme' => round($netIntermediaire - $reference['salaire_net'], 2),
                'frais_pro' => round($projete['salaire_net'] - $netIntermediaire, 2),
            ],
        ];
    }

    private function metric(array $metrics, string $cle): array
    {
        foreach ($metrics as $metric) {
            if ($metric['key'] === $cle) {
                return $metric;
            }
        }

        throw new \LogicException("Indicateur {$cle} absent du comparateur.");
    }
}
