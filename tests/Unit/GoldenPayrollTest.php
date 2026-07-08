<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

class GoldenPayrollTest extends TestCase
{
    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    public function test_smig_simple_sans_primes(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 3422.72,
            'type_frais_pro' => 'commun',
        ]);

        $this->assertSame(3422.72, $r['sbi']);
        $this->assertSame(153.34, $r['cotisation_cnss']);
        $this->assertSame(77.35, $r['cotisation_amo']);
        $this->assertSame(230.69, $r['total_sociales']);
        $this->assertSame(1197.95, $r['frais_pro']);
        $this->assertSame(1994.08, $r['rni']);
        $this->assertSame(0.0, $r['ir_net']);
        $this->assertSame(3192.03, $r['salaire_net']);
        $this->assertSame(3422.72, $r['salaire_brut_total']);
        $this->assertSame(4144.56, $r['cout_total_employeur']);
        $this->assertEmpty($r['avertissements']);
    }

    public function test_cadre_avec_anciennete_et_famille(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 15000,
            'type_frais_pro' => 'commun',
            'nb_annees_anciennete' => 8,
            'nb_enfants' => 3,
            'conjoint_charge' => true,
        ]);

        $this->assertSame(1500.0, $r['prime_anciennete']);
        $this->assertSame(0.10, $r['taux_anciennete']);
        $this->assertSame(16500.0, $r['sbi']);
        $this->assertSame(268.8, $r['cotisation_cnss']);
        $this->assertSame(372.9, $r['cotisation_amo']);
        $this->assertSame(2916.67, $r['frais_pro']);
        $this->assertTrue($r['fp_plafonne']);
        $this->assertSame(200.0, $r['charges_famille']);
        $this->assertSame(2366.82, $r['ir_net']);
        $this->assertSame(13491.48, $r['salaire_net']);
        $this->assertSame(19036.95, $r['cout_total_employeur']);
    }

    public function test_salarie_avec_cimr_partage(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'cimr_taux' => 6,
            'cimr_taux_employeur' => 3,
        ]);

        $this->assertSame(600.0, $r['cotisation_cimr']);
        $this->assertSame(300.0, $r['cotisation_cimr_patronale']);
        $this->assertSame(447.71, $r['ir_net']);
        $this->assertSame(8457.49, $r['salaire_net']);
        $this->assertSame(12049.8, $r['cout_total_employeur']);
    }

    public function test_indemnites_exonerees_et_excedent_imposable(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 8000,
            'type_frais_pro' => 'commun',
            'indemnites' => [
                ['type' => 'transport', 'montant' => 800],
                ['type' => 'representation', 'montant' => 1500],
            ],
        ]);

        $this->assertSame(9000.0, $r['sbi']);
        $this->assertSame(1300.0, $r['total_indemnites']);
        $this->assertSame(1000.0, $r['excedent_indemnites']);
        $this->assertSame(422.23, $r['ir_net']);
        $this->assertSame(9405.57, $r['salaire_net']);
        $this->assertSame(11928.7, $r['cout_total_employeur']);
        $this->assertNotEmpty($r['avertissements']);
    }

    public function test_mutuelle_salarie_reduit_l_assiette_ir_avant_le_bareme(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 12000,
            'type_frais_pro' => 'commun',
            'mutuelle_salarie' => 300,
        ]);

        $this->assertSame(300.0, $r['mutuelle_salarie']);
        $this->assertSame(300.0, $r['total_retenues']);
        $this->assertSame(8243.33, $r['rni']);
        $this->assertSame(973.0, $r['ir_net']);
        $this->assertSame(10187.0, $r['salaire_net']);
    }

    public function test_mutuelle_et_retraite_complementaire(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 12000,
            'type_frais_pro' => 'commun',
            'mutuelle_salarie' => 300,
            'mutuelle_patronale' => 500,
            'retraite_complementaire_mensuel' => 600,
            'rc_part_employeur' => 400,
        ]);

        $this->assertSame(7200.0, $r['rc_deduite']);
        $this->assertSame(300.0, $r['total_retenues']);
        $this->assertSame(793.0, $r['ir_net']);
        $this->assertSame(10367.0, $r['salaire_net']);
        $this->assertSame(14892.0, $r['cout_total_employeur']);
    }

    public function test_salaire_au_plafond_cnss(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 6000,
            'type_frais_pro' => 'commun',
        ]);

        $this->assertEquals(6000.0, $r['assiette_cnss']);
        $this->assertSame(268.8, $r['cotisation_cnss']);
        $this->assertSame(16.23, $r['ir_net']);
        $this->assertSame(5579.37, $r['salaire_net']);
        $this->assertSame(7265.4, $r['cout_total_employeur']);
    }

    public function test_journaliste_frais_pro_majores(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 10000,
            'type_frais_pro' => 'journaliste',
        ]);

        $this->assertSame(0.45, $r['taux_fp']);
        $this->assertSame(2916.67, $r['frais_pro']);
        $this->assertTrue($r['fp_plafonne']);
        $this->assertSame(484.37, $r['ir_net']);
        $this->assertSame(9020.83, $r['salaire_net']);
    }
}
