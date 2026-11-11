<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

/**
 * Sélection de la tranche du barème IR annuel (Art. 73 CGI, issue #272).
 *
 * Le moteur retient la première tranche dont le maximum contient le RNI annuel
 * net, borne supérieure incluse, traite un RNI nul à part et garde la dernière
 * tranche, sans maximum, pour tout le reste. Chaque maximum fini est encadré :
 * un centime en dessous, exactement au maximum, un centime au-dessus, et le
 * minimum entier de la tranche suivante.
 *
 * Le barème est continu : à chaque maximum, deux tranches adjacentes donnent le
 * même IR arrondi. Le montant seul ne prouve donc pas la tranche retenue ; on
 * affirme la tranche complète (taux et déduction compris) en plus du montant.
 *
 * Taux, déductions et seuils sont lus dans config('payroll.ir.baremes'), jamais
 * recopiés. La routine privée est appelée par réflexion, sans changer sa
 * visibilité : appeler calculer() ne permet pas d'atteindre exactement chaque
 * seuil annuel, le RNI mensuel étant arrondi au centime avant d'être annualisé.
 */
class BaremeIrTranchesTest extends TestCase
{
    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    /**
     * @return array{montant: float, tranche: array<string, mixed>}
     */
    private function selectionner(float $rniAnnuel): array
    {
        $methode = new \ReflectionMethod(PayrollCalculatorService::class, 'calculerIRAnnuelBrut');

        return $methode->invoke($this->calculator, $rniAnnuel);
    }

    /**
     * IR annuel brut d'une tranche : RNI × taux − déduction, plancher à zéro,
     * arrondi au centime (formule documentée dans config/payroll.php).
     *
     * @param  array<string, mixed>  $tranche
     */
    private function irAttendu(float $rniAnnuel, array $tranche): float
    {
        return round(max(0.0, $rniAnnuel * $tranche['taux'] - $tranche['deduction']), 2);
    }

    /**
     * Tranches dont le maximum est fini, avec leur index dans le barème.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tranchesBornees(): array
    {
        $bornees = array_filter(
            config('payroll.ir.baremes'),
            fn (array $tranche): bool => $tranche['max'] !== null,
        );

        $this->assertNotEmpty($bornees, 'le barème doit compter au moins une tranche bornée');

        return $bornees;
    }

    private function assertTrancheRetenue(float $rniAnnuel, int $indexAttendu, string $cas): void
    {
        $baremes = config('payroll.ir.baremes');
        $attendue = $baremes[$indexAttendu];

        $resultat = $this->selectionner($rniAnnuel);

        $this->assertSame(
            $attendue,
            $resultat['tranche'],
            sprintf('%s : RNI annuel %.2f doit retenir la tranche %d', $cas, $rniAnnuel, $indexAttendu),
        );
        $this->assertSame(
            $this->irAttendu($rniAnnuel, $attendue),
            $resultat['montant'],
            sprintf('%s : IR annuel brut pour un RNI annuel de %.2f', $cas, $rniAnnuel),
        );
    }

    public function test_rni_nul_retient_la_premiere_tranche_sans_impot(): void
    {
        $resultat = $this->selectionner(0.0);

        $this->assertSame(config('payroll.ir.baremes')[0], $resultat['tranche']);
        $this->assertSame(0.0, $resultat['montant']);
    }

    public function test_un_centime_sous_chaque_maximum_reste_dans_la_tranche(): void
    {
        foreach ($this->tranchesBornees() as $index => $tranche) {
            $rni = round($tranche['max'] - 0.01, 2);
            $this->assertGreaterThanOrEqual($tranche['min'], $rni, "tranche {$index} : saisie dans la tranche");

            $this->assertTrancheRetenue($rni, $index, 'sous le maximum');
        }
    }

    public function test_exactement_au_maximum_reste_dans_la_tranche(): void
    {
        foreach ($this->tranchesBornees() as $index => $tranche) {
            $this->assertTrancheRetenue((float) $tranche['max'], $index, 'au maximum (borne incluse)');
        }
    }

    public function test_un_centime_au_dessus_du_maximum_passe_a_la_tranche_suivante(): void
    {
        foreach ($this->tranchesBornees() as $index => $tranche) {
            $this->assertTrancheRetenue(round($tranche['max'] + 0.01, 2), $index + 1, 'au-dessus du maximum');
        }
    }

    public function test_minimum_de_la_tranche_suivante_retient_cette_tranche(): void
    {
        $baremes = config('payroll.ir.baremes');

        foreach ($this->tranchesBornees() as $index => $tranche) {
            $this->assertTrancheRetenue((float) $baremes[$index + 1]['min'], $index + 1, 'au minimum suivant');
        }
    }

    public function test_au_dela_du_dernier_maximum_retient_la_tranche_non_bornee(): void
    {
        $baremes = config('payroll.ir.baremes');
        $derniereIndex = array_key_last($baremes);
        $this->assertNull($baremes[$derniereIndex]['max'], 'la dernière tranche est sans maximum');

        $dernierMaximum = (float) $baremes[$derniereIndex - 1]['max'];

        $this->assertTrancheRetenue($dernierMaximum * 2, $derniereIndex, 'dernière tranche');
    }

    /**
     * Raison des assertions de tranche : à chaque maximum, la tranche et sa
     * suivante donnent le même IR arrondi. Une inversion de la borne (<= en <)
     * ne changerait donc pas le montant au seuil, seulement la tranche retenue.
     */
    public function test_le_bareme_est_continu_a_chaque_maximum(): void
    {
        $baremes = config('payroll.ir.baremes');

        foreach ($this->tranchesBornees() as $index => $tranche) {
            $max = (float) $tranche['max'];

            $this->assertSame(
                $this->irAttendu($max, $tranche),
                $this->irAttendu($max, $baremes[$index + 1]),
                "continuité du barème au maximum de la tranche {$index}",
            );
        }
    }

    /**
     * calculer() expose la tranche retenue pour le RNI annuel net qu'il a
     * réellement atteint, avec le montant correspondant.
     */
    public function test_calculer_expose_la_tranche_du_rni_annuel_net_atteint(): void
    {
        foreach ([3000.0, 6000.0, 8000.0, 10000.0, 15000.0, 30000.0] as $base) {
            $result = $this->calculator->calculer(['salaire_base' => $base]);

            $attendu = $this->selectionner($result['rni_annuel_net']);

            $this->assertSame($attendu['tranche'], $result['tranche_ir'], "tranche exposée pour une base de {$base}");
            $this->assertSame($attendu['montant'], $result['ir_annuel_brut'], "IR annuel brut pour une base de {$base}");
        }
    }
}
