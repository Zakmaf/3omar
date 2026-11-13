<?php

namespace Tests\Unit;

use App\Services\ExercicesFiscaux;
use App\Services\PayrollCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Oracle indépendant des barèmes IR 2026 à 2031 (issue #281).
 *
 * Les tables ci-dessous recopient volontairement les seuils et taux marginaux
 * des textes sources, sans aucune déduction : 2026 depuis la Loi de Finances
 * 50-25 (barème historique), 2027 à 2030 depuis l'art. 247-XXXXVII du PLF
 * n° 60-26 (texte déposé), 2031 depuis l'art. 73-I modifié du même projet.
 * Elles servent d'oracle, pas de source applicative : la production lit
 * config/payroll.php. L'impôt de l'oracle est la somme des contributions de
 * chaque tranche, calcul progressif qui ne dépend pas des déductions dérivées.
 *
 * La routine du moteur est privée : elle est appelée par réflexion, comme dans
 * BaremeIrTranchesTest, sans changer sa visibilité.
 */
class BaremeIrOracleTest extends TestCase
{
    /** @var array<int, list<array{0: int|null, 1: float}>> [max inclus, taux marginal] */
    private const TABLES = [
        2026 => [[40000, 0.00], [60000, 0.10], [80000, 0.20], [100000, 0.30], [180000, 0.34], [null, 0.37]],
        2027 => [[60000, 0.00], [80000, 0.10], [100000, 0.20], [180000, 0.34], [null, 0.37]],
        2028 => [[60000, 0.00], [100000, 0.10], [200000, 0.25], [null, 0.37]],
        2029 => [[60000, 0.00], [130000, 0.10], [200000, 0.20], [null, 0.35]],
        2030 => [[60000, 0.00], [150000, 0.10], [200000, 0.20], [null, 0.30]],
        2031 => [[60000, 0.00], [150000, 0.10], [500000, 0.20], [null, 0.30]],
    ];

    /**
     * @return array<string, array{int}>
     */
    public static function exercices(): array
    {
        $cas = [];
        foreach (array_keys(self::TABLES) as $annee) {
            $cas[(string) $annee] = [$annee];
        }

        return $cas;
    }

    /**
     * @return array{montant: float, tranche: array<string, mixed>}
     */
    private function moteur(float $rni, int $exercice): array
    {
        $methode = new \ReflectionMethod(PayrollCalculatorService::class, 'calculerIRAnnuelBrut');

        return $methode->invoke(app(PayrollCalculatorService::class), $rni, $exercice);
    }

    /**
     * Contributions de chaque tranche, non arrondies.
     *
     * @return list<float>
     */
    private function contributions(float $rni, int $exercice): array
    {
        $precedent = 0.0;
        $contributions = [];

        foreach (self::TABLES[$exercice] as [$max, $taux]) {
            $haut = $max === null ? $rni : min($rni, (float) $max);
            $contributions[] = max(0.0, $haut - $precedent) * $taux;

            if ($max === null || $rni <= $max) {
                break;
            }
            $precedent = (float) $max;
        }

        return $contributions;
    }

    /**
     * @return list<float>
     */
    private function points(int $exercice): array
    {
        $points = [0.0, 0.01];
        $dernierMax = 0;

        foreach (self::TABLES[$exercice] as [$max]) {
            if ($max === null) {
                continue;
            }
            array_push($points, round($max - 0.01, 2), (float) $max, round($max + 0.01, 2), (float) ($max + 1));
            $dernierMax = $max;
        }

        array_push($points, (float) ($dernierMax * 2), 1234567.89);
        sort($points);

        return $points;
    }

    #[DataProvider('exercices')]
    public function test_la_configuration_reprend_les_seuils_et_taux_du_texte_source(int $exercice): void
    {
        $baremes = app(ExercicesFiscaux::class)->parametre($exercice, 'ir_baremes');

        $this->assertIsArray($baremes, "{$exercice} : barème résolu");
        $this->assertSame(
            self::TABLES[$exercice],
            array_map(fn (array $t): array => [$t['max'], (float) $t['taux']], $baremes),
            "{$exercice} : seuils et taux marginaux",
        );

        $minimumAttendu = 0;
        foreach ($baremes as $index => $tranche) {
            $this->assertEquals($minimumAttendu, $tranche['min'], "{$exercice} : minimum entier de la tranche {$index}");
            $minimumAttendu = $tranche['max'] === null ? null : $tranche['max'] + 1;
        }
    }

    #[DataProvider('exercices')]
    public function test_le_moteur_egale_l_oracle_progressif_aux_seuils(int $exercice): void
    {
        $precedent = -1.0;

        foreach ($this->points($exercice) as $rni) {
            $contributions = $this->contributions($rni, $exercice);
            $oracle = round(array_sum($contributions), 2);
            $sommeArrondie = array_sum(array_map(fn (float $c): float => round($c, 2), $contributions));
            $montant = $this->moteur($rni, $exercice)['montant'];

            $this->assertEqualsWithDelta($oracle, $montant, 0.011, "{$exercice} : IR pour un RNI de {$rni}");
            $this->assertEqualsWithDelta(
                $sommeArrondie,
                $montant,
                0.005 * count($contributions) + 0.011,
                "{$exercice} : somme des contributions arrondies pour un RNI de {$rni}",
            );
            $this->assertGreaterThanOrEqual(0.0, $montant, "{$exercice} : IR non négatif pour {$rni}");
            $this->assertGreaterThanOrEqual($precedent, $montant, "{$exercice} : IR croissant avec le RNI ({$rni})");
            $precedent = $montant;
        }
    }

    #[DataProvider('exercices')]
    public function test_les_deductions_derivees_sont_continues_a_chaque_seuil(int $exercice): void
    {
        $baremes = app(ExercicesFiscaux::class)->parametre($exercice, 'ir_baremes');

        foreach ($baremes as $index => $tranche) {
            if ($tranche['max'] === null) {
                continue;
            }
            $suivante = $baremes[$index + 1];
            $max = (float) $tranche['max'];

            $this->assertEqualsWithDelta(
                $max * $tranche['taux'] - $tranche['deduction'],
                $max * $suivante['taux'] - $suivante['deduction'],
                0.001,
                "{$exercice} : continuité au seuil {$tranche['max']}",
            );
        }
    }

    #[DataProvider('exercices')]
    public function test_la_derniere_tranche_est_non_bornee_et_retenue_au_dela_du_dernier_seuil(int $exercice): void
    {
        $table = self::TABLES[$exercice];
        $dernierMax = $table[count($table) - 2][0];

        $resultat = $this->moteur((float) ($dernierMax * 2), $exercice);

        $this->assertNull($resultat['tranche']['max'], "{$exercice} : tranche non bornée");
        $this->assertSame((float) $table[count($table) - 1][1], (float) $resultat['tranche']['taux']);
    }
}
