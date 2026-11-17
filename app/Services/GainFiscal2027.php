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
 *   dans l'ordre explicite barème puis frais professionnels. Les deux parts
 *   et le gain sont arrondis au centime : leur somme vaut le gain au centime
 *   après arrondi, jamais par addition flottante brute.
 *
 * Saisie source :
 * - l'exercice de la saisie reçue (absent : repli 2026) est exposé dans
 *   `source`, jamais écrasé en silence. Une saisie 2027 est recalculée en
 *   2026 et en 2027 ; un exercice non calculable est refusé ;
 * - net cible (net_to_gross) : une seule résolution, en 2026, puis le salaire
 *   de base trouvé est figé pour les deux exercices, les autres entrées étant
 *   conservées. Un net cible d'une saisie d'un autre exercice que 2026 est
 *   refusé avant toute résolution : le résoudre en 2026 changerait en silence
 *   le sens de la cible. Une cible que cette résolution n'atteint pas
 *   (resolution_net.converge faux) est refusée avant tout calcul de gain.
 *
 * La projection annuelle est indicative : douze mois identiques
 * (config('payroll.ir.nb_mois')), sans prime ponctuelle ni année réelle (#44).
 *
 * Le résultat intermédiaire n'appartient à aucun exercice : seul son net
 * est exposé (decomposition.net_intermediaire), jamais le résultat complet.
 */
class GainFiscal2027
{
    public const EXERCICE_REFERENCE = 2026;

    public const EXERCICE_PROJETE = 2027;

    /** Refus d'un net cible d'un exercice autre que 2026. */
    public const REFUS_NET_SOURCE_2027 = 'ui.gain2027.net_source_2027_refused';

    /** Refus d'un net cible que la résolution 2026 n'atteint pas. */
    public const REFUS_NET_NON_ATTEINT = 'ui.gain2027.net_target_not_reached';

    private const MODES = ['gross_to_net', 'net_to_gross'];

    /** Ordre de la décomposition : chaque étape change un seul paramètre. */
    private const ORDRE = ['bareme', 'frais_pro'];

    public function __construct(
        private PayrollCalculatorService $moteur,
        private SimulationComparator $comparateur,
        private ExercicesFiscaux $exercices,
    ) {}

    /**
     * @return array{
     *     mode: string,
     *     source: array{exercice: int, mode: string},
     *     exercices: array{reference: int, projete: int},
     *     resolution: array{exercice: int, net_cible: float, salaire_base: float, converge: bool, ecart: float}|null,
     *     reference: array,
     *     projete: array,
     *     metrics: list<array<string, mixed>>,
     *     gain: array{net_mensuel: float, net_pct: float|null, nb_mois: int, net_annuel_indicatif: float, aucun_gain_ir: bool},
     *     decomposition: array{ordre: list<string>, net_intermediaire: float, bareme: float, frais_pro: float}
     * }
     */
    public function comparer(array $saisie): array
    {
        $source = $this->source($saisie);

        if ($this->sourceNetRefusee($saisie)) {
            throw new \InvalidArgumentException(__(self::REFUS_NET_SOURCE_2027));
        }

        $resolution = null;
        $saisieCalcul = $saisie;

        if ($source['mode'] === 'net_to_gross') {
            [$saisieCalcul, $resolution] = $this->figerBrutDepuisNet($saisie);
        }

        $calculs = $this->moteur->decomposerChangementExercice($saisieCalcul, self::EXERCICE_REFERENCE, self::EXERCICE_PROJETE);
        $reference = $calculs['reference'];
        $projete = $calculs['projete'];
        $netIntermediaire = $calculs['intermediaire']['salaire_net'];

        $metrics = $this->comparateur->metrics($reference, $projete);
        $net = $this->metric($metrics, 'salaire_net');
        $nbMois = (int) config('payroll.ir.nb_mois');

        return [
            'mode' => $source['mode'],
            'source' => $source,
            'exercices' => ['reference' => self::EXERCICE_REFERENCE, 'projete' => self::EXERCICE_PROJETE],
            'resolution' => $resolution,
            'reference' => $reference,
            'projete' => $projete,
            'metrics' => $metrics,
            'gain' => [
                'net_mensuel' => $net['ecart'],
                'net_pct' => $net['ecart_pct'],
                'nb_mois' => $nbMois,
                'net_annuel_indicatif' => round($net['ecart'] * $nbMois, 2),
                // IR 2026 déjà nul : aucun gain d'IR ne peut être annoncé.
                'aucun_gain_ir' => (float) $reference['ir_net'] <= 0.0,
            ],
            'decomposition' => [
                'ordre' => self::ORDRE,
                'net_intermediaire' => $netIntermediaire,
                'bareme' => round($netIntermediaire - $reference['salaire_net'], 2),
                'frais_pro' => round($projete['salaire_net'] - $netIntermediaire, 2),
            ],
        ];
    }

    /**
     * Vrai si la saisie est un net cible d'un exercice autre que 2026 : elle
     * est refusée, jamais résolue en 2026 en silence. Partagé avec le
     * contrôleur, qui affiche alors une notice dédiée.
     */
    public function sourceNetRefusee(array $saisie): bool
    {
        return ($saisie['mode'] ?? 'gross_to_net') === 'net_to_gross'
            && $this->exercices->resoudre($saisie['exercice'] ?? null) !== self::EXERCICE_REFERENCE;
    }

    /**
     * Motif (clé de traduction) d'un refus certain de la page de gain pour un
     * résultat déjà calculé, ou null. La page résultat remplace alors l'appel
     * à l'action par une notice, sans aucun calcul supplémentaire :
     * - net cible d'un autre exercice que 2026 (sourceNetRefusee) ;
     * - net cible 2026 non atteint : la page de gain rejouerait la même
     *   résolution 2026, sur la même saisie, et la refuserait.
     */
    public function motifRefus(array $saisie, array $resultat): ?string
    {
        if ($this->sourceNetRefusee($saisie)) {
            return self::REFUS_NET_SOURCE_2027;
        }

        $netCible = ($resultat['mode'] ?? null) === 'net_to_gross';
        $nonAtteint = ! ($resultat['resolution_net']['converge'] ?? true);

        return $netCible && $nonAtteint ? self::REFUS_NET_NON_ATTEINT : null;
    }

    /**
     * Mode et exercice de la saisie reçue. Un mode inconnu ou un exercice non
     * calculable est refusé avec un message traduit.
     *
     * @return array{exercice: int, mode: string}
     */
    private function source(array $saisie): array
    {
        $mode = $saisie['mode'] ?? 'gross_to_net';

        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException(__('ui.gain2027.mode_unsupported'));
        }

        $erreur = $this->exercices->erreur($saisie['exercice'] ?? null);

        if ($erreur !== null) {
            throw new \InvalidArgumentException($erreur);
        }

        return ['exercice' => $this->exercices->resoudre($saisie['exercice'] ?? null), 'mode' => $mode];
    }

    /**
     * Une seule résolution net vers brut, en 2026. La saisie recalculée garde
     * toutes les autres entrées et remplace le net cible par le salaire de
     * base trouvé, identique pour les deux exercices.
     *
     * @return array{0: array, 1: array{exercice: int, net_cible: float, salaire_base: float, converge: bool, ecart: float}}
     */
    private function figerBrutDepuisNet(array $saisie): array
    {
        $resolu = $this->moteur->resoudreDepuisNet(array_merge($saisie, ['exercice' => self::EXERCICE_REFERENCE]));

        // Cible non atteinte (hors borne du solveur, déjà dépassée à salaire
        // de base nul, ou non-convergence) : le brut trouvé ne donne pas le net
        // visé. Refus avant tout calcul de gain, jamais une comparaison
        // présentée comme « à net cible ».
        if (! $resolu['resolution_net']['converge']) {
            throw new \InvalidArgumentException(__(self::REFUS_NET_NON_ATTEINT));
        }

        // Candidat de la bissection, non arrondi : les deux exercices le
        // reçoivent tel quel (voir docs/CALCUL.md, précision du brut figé).
        $salaireBase = (float) $resolu['input']['salaire_base'];

        $saisieFigee = array_merge(
            array_diff_key($saisie, ['net_cible' => true]),
            ['mode' => 'gross_to_net', 'salaire_base' => $salaireBase],
        );

        return [$saisieFigee, [
            'exercice' => self::EXERCICE_REFERENCE,
            'net_cible' => $resolu['resolution_net']['net_cible'],
            'salaire_base' => $salaireBase,
            'converge' => $resolu['resolution_net']['converge'],
            'ecart' => $resolu['resolution_net']['ecart'],
        ]];
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
