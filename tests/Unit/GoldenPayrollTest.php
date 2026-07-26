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

    public function test_amo_taux_legal_reste_applique_par_defaut(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 3422.72,
            'type_frais_pro' => 'commun',
        ]);

        $this->assertFalse($r['amo_taux_salarie_personnalise']);
        $this->assertSame(0.0226, $r['amo_taux_salarie']);
        $this->assertSame(77.35, $r['cotisation_amo']);
        $this->assertEmpty($r['avertissements']);
    }

    public function test_amo_salarie_personnalise_permet_de_reproduire_un_bulletin_reel(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 3422.72,
            'type_frais_pro' => 'commun',
            'amo_taux_salarie_personnalise' => true,
            'amo_taux_salarie' => 0,
        ]);

        $this->assertTrue($r['amo_taux_salarie_personnalise']);
        $this->assertSame(0.0, $r['amo_taux_salarie']);
        $this->assertSame(0.0, $r['cotisation_amo']);
        $this->assertSame(153.34, $r['total_sociales']);
        $this->assertSame(2071.43, $r['rni']);
        $this->assertSame(0.0, $r['ir_net']);
        $this->assertSame(3269.38, $r['salaire_net']);
        // Le cout employeur n'est pas affecte : la part patronale de l'AMO
        // reste au taux legal, seule la part salariale est derogatoire.
        $this->assertSame(4144.56, $r['cout_total_employeur']);
        $this->assertNotEmpty($r['avertissements']);
        $this->assertStringContainsString('AMO', $r['avertissements'][0]);
    }

    public function test_amo_salarie_personnalise_avec_un_taux_non_nul(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 3422.72,
            'type_frais_pro' => 'commun',
            'amo_taux_salarie_personnalise' => true,
            'amo_taux_salarie' => 1.5,
        ]);

        $this->assertTrue($r['amo_taux_salarie_personnalise']);
        $this->assertSame(0.015, $r['amo_taux_salarie']);
        $this->assertSame(51.34, $r['cotisation_amo']);
        $this->assertSame(204.68, $r['total_sociales']);
        $this->assertSame(2020.09, $r['rni']);
        $this->assertSame(3218.04, $r['salaire_net']);
        $this->assertSame(4144.56, $r['cout_total_employeur']);
        $this->assertNotEmpty($r['avertissements']);
        $this->assertStringContainsString('AMO', $r['avertissements'][0]);
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

    /**
     * Golden test de démonstration 2026 (issue #139).
     *
     * Profil entièrement fictif et arrondi, construit pour exercer d'un seul coup
     * l'ancienneté, une prime imposable, une indemnité exonérée, la mutuelle
     * pré-fiscale, la CIMR et les charges de famille.
     *
     * Hypothèses de la fixture :
     *  - 5 années d'ancienneté, soit la tranche 5 à 11 ans (10%) de
     *    config('payroll.anciennete.tranches') retenue comme tranche standard ;
     *  - indemnité de transport déclarée à son plafond d'exonération configuré,
     *    donc intégralement exonérée et sans excédent réintégré au SBI ;
     *  - 2 personnes à charge saisies comme 2 enfants, sans conjoint à charge ;
     *  - aucune heure supplémentaire, aucune retenue autre que la mutuelle.
     *
     * Les montants attendus sont vérifiables à la main depuis config/payroll.php :
     *  ancienneté  10 000 x 10%                        = 1 000,00
     *  SBI         10 000 + 1 000 + 1 500              = 12 500,00
     *  CNSS        min(12 500 ; 6 000) x 4,48%         =    268,80
     *  AMO         12 500 x 2,26%                      =    282,50
     *  CIMR        12 500 x 6%                         =    750,00
     *  SNC         12 500 - 1 301,30                   = 11 198,70
     *  frais pro   12 500 x 25% = 3 125 > plafond      =  2 916,67
     *  RNI         11 198,70 - 2 916,67 - 250          =  8 032,03
     *  IR annuel   96 384,36 x 30% - 18 000            = 10 915,31
     *  IR net      909,61 - (2 x 50)                   =    809,61
     *  net         12 500 - 268,80 - 282,50 - 750
     *              - 809,61 + 500 - 250                = 10 639,09
     */
    public function test_golden_profil_demonstration_2026(): void
    {
        $r = $this->calculator->calculer([
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'nb_annees_anciennete' => 5,
            'autres_primes' => 1500,
            'indemnites' => [['type' => 'transport', 'montant' => 500]],
            'mutuelle_salarie' => 250,
            'cimr_taux' => 6,
            'nb_enfants' => 2,
        ]);

        // 1. Salaire brut imposable
        $this->assertSame(0.10, $r['taux_anciennete']);
        $this->assertSame(1000.0, $r['prime_anciennete']);
        $this->assertSame(2500.0, $r['total_primes']);
        $this->assertSame(12500.0, $r['sbi']);
        $this->assertSame(500.0, $r['total_indemnites']);
        $this->assertSame(0.0, $r['excedent_indemnites'], "L'indemnité de transport est au plafond : aucun excédent imposable.");

        // 2. Cotisations sociales
        $this->assertSame(268.8, $r['cotisation_cnss']);
        $this->assertSame(282.5, $r['cotisation_amo']);
        $this->assertSame(750.0, $r['cotisation_cimr']);
        $this->assertSame(1301.3, $r['total_sociales']);

        // 3. Revenu net imposable
        $this->assertSame(11198.7, $r['snc']);
        $this->assertSame(2916.67, $r['frais_pro']);
        $this->assertTrue($r['fp_plafonne']);
        $this->assertSame(8032.03, $r['rni'], 'La mutuelle salarié est déduite avant le barème IR.');
        $this->assertSame(96384.36, $r['rni_annuel']);

        // 4. IR mensuel
        $this->assertSame(10915.31, $r['ir_annuel_brut']);
        $this->assertSame(909.61, $r['ir_mensuel_brut']);
        $this->assertSame(2, $r['nb_personnes']);
        $this->assertSame(100.0, $r['charges_famille']);
        $this->assertSame(809.61, $r['ir_net']);

        // 5. Salaire net final et coût employeur
        $this->assertSame(250.0, $r['total_retenues']);
        $this->assertSame(10639.09, $r['salaire_net']);
        $this->assertSame(13000.0, $r['salaire_brut_total']);
        $this->assertSame(2052.55, $r['total_patronal']);
        $this->assertSame(15052.55, $r['cout_total_employeur']);

        // 6. Aucun avertissement : le profil reste dans toutes les limites légales.
        $this->assertEmpty($r['avertissements']);
    }
}
