<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use App\Services\SimulationComparator;
use Tests\TestCase;

class SimulationComparatorTest extends TestCase
{
    private SimulationComparator $comparator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comparator = app(SimulationComparator::class);
    }

    private function metric(array $metrics, string $label): array
    {
        foreach ($metrics as $metric) {
            if ($metric['label'] === $label) {
                return $metric;
            }
        }

        $this->fail("Indicateur {$label} absent de la comparaison.");
    }

    public function test_une_augmentation_de_salaire_produit_les_ecarts_attendus(): void
    {
        $calculator = app(PayrollCalculatorService::class);

        $a = $calculator->calculer(['salaire_base' => 10000, 'type_frais_pro' => 'commun']);
        $b = $calculator->calculer(['salaire_base' => 12000, 'type_frais_pro' => 'commun']);

        $metrics = $this->comparator->metrics($a, $b);

        $net = $this->metric($metrics, 'net');
        $this->assertSame($a['salaire_net'], $net['a']);
        $this->assertSame($b['salaire_net'], $net['b']);
        $this->assertSame(round($b['salaire_net'] - $a['salaire_net'], 2), $net['ecart']);
        $this->assertGreaterThan(0, $net['ecart'], 'Un salaire plus élevé doit augmenter le net.');

        // Le brut augmente de 2 000 MAD : l'écart relatif est donc de 20%.
        $brut = $this->metric($metrics, 'gross_total');
        $this->assertSame(2000.0, $brut['ecart']);
        $this->assertSame(20.0, $brut['ecart_pct']);

        $cout = $this->metric($metrics, 'employer_cost');
        $this->assertGreaterThan(0, $cout['ecart']);
        $this->assertSame('charge', $cout['sens']);
    }

    public function test_l_ecart_relatif_est_absent_quand_le_scenario_a_vaut_zero(): void
    {
        $calculator = app(PayrollCalculatorService::class);

        // Un SMIG ne paie pas d'IR : l'IR du scénario A vaut zéro.
        $a = $calculator->calculer(['salaire_base' => config('payroll.smig.mensuel'), 'type_frais_pro' => 'commun']);
        $b = $calculator->calculer(['salaire_base' => 20000, 'type_frais_pro' => 'commun']);

        $ir = $this->metric($this->comparator->metrics($a, $b), 'ir');

        $this->assertSame(0.0, $ir['a']);
        $this->assertGreaterThan(0, $ir['b']);
        $this->assertNull($ir['ecart_pct']);
    }

    public function test_deux_scenarios_identiques_ne_produisent_aucun_ecart(): void
    {
        $r = app(PayrollCalculatorService::class)->calculer(['salaire_base' => 10000, 'type_frais_pro' => 'commun']);

        foreach ($this->comparator->metrics($r, $r) as $metric) {
            $this->assertSame(0.0, $metric['ecart'], $metric['label']);
        }
    }

    public function test_les_indicateurs_phares_sont_le_net_et_le_cout_employeur(): void
    {
        $r = app(PayrollCalculatorService::class)->calculer(['salaire_base' => 10000, 'type_frais_pro' => 'commun']);

        $labels = array_column($this->comparator->highlights($r, $r), 'label');

        $this->assertSame(['net', 'employer_cost'], $labels);
    }

    public function test_les_ecarts_d_entrees_listent_uniquement_les_champs_modifies(): void
    {
        $differences = $this->comparator->inputDifferences(
            ['salaire_base' => 10000, 'type_frais_pro' => 'commun', 'cimr_taux' => 6],
            ['salaire_base' => 12000, 'type_frais_pro' => 'commun', 'cimr_taux' => 6],
        );

        $this->assertSame(['salaire_base'], array_column($differences, 'field'));
    }

    /**
     * Un champ vide, absent ou à zéro décrit la même situation dans le
     * formulaire : ce n'est pas un écart entre les deux scénarios.
     */
    public function test_les_valeurs_vides_absentes_et_nulles_sont_equivalentes(): void
    {
        $differences = $this->comparator->inputDifferences(
            ['salaire_base' => 10000, 'autres_primes' => 0, 'mutuelle_salarie' => ''],
            ['salaire_base' => 10000],
        );

        $this->assertSame([], $differences);
    }

    public function test_un_ecart_sur_une_ligne_repetable_est_detecte(): void
    {
        $differences = $this->comparator->inputDifferences(
            ['salaire_base' => 10000, 'indemnites' => [['type' => 'transport', 'montant' => 500]]],
            ['salaire_base' => 10000, 'indemnites' => [['type' => 'transport', 'montant' => 300]]],
        );

        $this->assertSame(['indemnites'], array_column($differences, 'field'));
    }
}
