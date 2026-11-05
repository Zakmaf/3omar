<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bornes des tranches de la prime d'ancienneté (Art. 350 du Code du travail,
 * issue #239).
 *
 * Le moteur retient la première tranche de config('payroll.anciennete.tranches')
 * telle que min_annees <= n <= max_annees (max_annees null : sans borne haute),
 * puis calcule la prime sur le salaire de base, arrondie au centime. Le test
 * fige l'appartenance de chaque borne à sa tranche ; les taux sont lus dans la
 * configuration, jamais recopiés.
 */
class AncienneteTest extends TestCase
{
    /**
     * Base dont le produit par chaque taux de tranche tombe hors du centime :
     * l'arrondi de la prime est donc exercé à chaque borne.
     */
    private const BASE = 13333.33;

    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    /**
     * Années d'ancienneté et index attendu de la tranche dans la configuration
     * (null : aucune prime).
     *
     * @return array<string, array{0: int, 1: int|null}>
     */
    public static function bornes(): array
    {
        return [
            '0 an' => [0, null],
            '1 an' => [1, null],
            '2 ans, debut de la 1re tranche' => [2, 0],
            '4 ans, fin de la 1re tranche' => [4, 0],
            '5 ans, debut de la 2e tranche' => [5, 1],
            '11 ans, fin de la 2e tranche' => [11, 1],
            '12 ans, debut de la 3e tranche' => [12, 2],
            '19 ans, fin de la 3e tranche' => [19, 2],
            '20 ans, debut de la 4e tranche' => [20, 3],
            '24 ans, fin de la 4e tranche' => [24, 3],
            '25 ans, debut de la 5e tranche' => [25, 4],
            '50 ans, maximum accepte par la validation' => [50, 4],
        ];
    }

    #[DataProvider('bornes')]
    public function test_chaque_borne_retient_sa_tranche(int $annees, ?int $tranche): void
    {
        $tauxAttendu = $tranche === null ? 0.0 : config("payroll.anciennete.tranches.{$tranche}.taux");

        $result = $this->calculator->calculer([
            'salaire_base' => self::BASE,
            'nb_annees_anciennete' => $annees,
        ]);

        $this->assertSame($tauxAttendu, $result['taux_anciennete'], "taux pour {$annees} an(s)");
        $this->assertSame(round(self::BASE * $tauxAttendu, 2), $result['prime_anciennete'], "prime pour {$annees} an(s)");
    }

    public function test_la_prime_est_arrondie_au_centime(): void
    {
        foreach (config('payroll.anciennete.tranches') as $index => $tranche) {
            $brut = self::BASE * $tranche['taux'];

            // Garde-fou : la base doit bien produire des fractions de centime.
            $this->assertNotEquals(round($brut, 2), $brut, "tranche {$index} : base sans fraction de centime");

            $result = $this->calculator->calculer([
                'salaire_base' => self::BASE,
                'nb_annees_anciennete' => $tranche['min_annees'],
            ]);

            $this->assertSame(round($brut, 2), $result['prime_anciennete'], "tranche {$index}");
        }
    }

    public function test_les_tranches_sont_contigues_et_couvrent_toute_anciennete_des_deux_ans(): void
    {
        $tranches = config('payroll.anciennete.tranches');

        $this->assertSame(2, $tranches[0]['min_annees']);
        $this->assertNull(end($tranches)['max_annees']);

        for ($i = 1; $i < count($tranches); $i++) {
            $this->assertSame(
                $tranches[$i - 1]['max_annees'] + 1,
                $tranches[$i]['min_annees'],
                "trou ou chevauchement entre les tranches {$i} et ".($i + 1),
            );
        }
    }
}
