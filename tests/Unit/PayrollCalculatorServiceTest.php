<?php

namespace Tests\Unit;

use App\Services\PayrollCalculatorService;
use Tests\TestCase;

class PayrollCalculatorServiceTest extends TestCase
{
    private PayrollCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);
    }

    public function test_cimr_half_percentage_is_not_rounded_to_next_integer(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 10000,
            'cimr_taux' => 3.5,
        ]);

        $this->assertSame(0.035, $result['cimr_taux']);
        $this->assertSame(350.0, $result['cotisation_cimr']);
    }

    public function test_unknown_overtime_type_is_ignored(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 5000,
            'heures_sup' => [['type' => 'inconnu', 'nb_heures' => 10]],
        ]);

        $this->assertSame([], $result['detail_hs']);
        $this->assertSame(5000.0, $result['sbi']);
    }

    public function test_professional_expenses_use_taxable_gross_income_and_shared_ceiling(): void
    {
        $result = $this->calculator->calculer(['salaire_base' => 5000]);

        $this->assertSame(1750.0, $result['frais_pro']);
        $this->assertSame(2916.67, $result['plafond_fp']);
    }

    public function test_indemnity_excess_is_reintegrated_into_taxable_gross_income(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 5000,
            'indemnites' => [['type' => 'transport', 'montant' => 800]],
        ]);

        $this->assertSame(300.0, $result['excedent_indemnites']);
        $this->assertSame(5300.0, $result['sbi']);
        $this->assertSame(500.0, $result['total_indemnites']);
    }

    public function test_daily_meal_allowance_ceiling_uses_worked_days(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 5000,
            'jours_travailles' => 20,
            'indemnites' => [['type' => 'panier', 'montant' => 800]],
        ]);

        $this->assertSame(716.8, $result['detail_indemnites'][0]['plafond']);
        $this->assertSame(83.2, $result['excedent_indemnites']);
    }

    public function test_duplicate_allowances_share_one_ceiling(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 5000,
            'indemnites' => [
                ['type' => 'transport', 'montant' => 400],
                ['type' => 'transport', 'montant' => 400],
            ],
        ]);

        $this->assertCount(1, $result['detail_indemnites']);
        $this->assertSame(500.0, $result['total_indemnites']);
        $this->assertSame(300.0, $result['excedent_indemnites']);
    }

    public function test_employer_cost_includes_exempt_allowances_paid_to_employee(): void
    {
        $withoutAllowance = $this->calculator->calculer(['salaire_base' => 5000]);
        $withAllowance = $this->calculator->calculer([
            'salaire_base' => 5000,
            'indemnites' => [['type' => 'transport', 'montant' => 500]],
        ]);

        $this->assertSame(
            $withoutAllowance['cout_total_employeur'] + 500,
            $withAllowance['cout_total_employeur'],
        );
    }

    public function test_bancassurance_amount_changes_tax_base_but_not_payroll_retentions(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 10000,
            'retraite_complementaire_mensuel' => 500,
        ]);

        $this->assertSame(6000.0, $result['rc_deduite']);
        $this->assertSame(0.0, $result['total_retenues']);
    }

    public function test_smig_warning_uses_base_salary_not_total_taxable_income(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 3000,
            'prime_bilan' => 1000,
        ]);

        $this->assertNotEmpty($result['avertissements']);
        $this->assertStringContainsString('salaire de base', $result['avertissements'][0]);
    }

    public function test_net_to_gross_resolution_recovers_simple_base_salary(): void
    {
        $direct = $this->calculator->calculer(['salaire_base' => 8500]);

        $resolved = $this->calculator->resoudreDepuisNet([
            'net_cible' => $direct['salaire_net'],
            'type_frais_pro' => 'commun',
        ]);

        $this->assertSame('net_to_gross', $resolved['mode']);
        $this->assertTrue($resolved['resolution_net']['converge']);
        $this->assertLessThanOrEqual(0.01, $resolved['resolution_net']['ecart']);
        $this->assertEqualsWithDelta(8500, $resolved['input']['salaire_base'], 0.05);
    }

    public function test_net_to_gross_resolution_recovers_salary_with_family_deductions(): void
    {
        $input = [
            'salaire_base' => 12000,
            'type_frais_pro' => 'commun',
            'nb_enfants' => 2,
            'conjoint_charge' => true,
        ];
        $direct = $this->calculator->calculer($input);

        $resolved = $this->calculator->resoudreDepuisNet([
            'net_cible' => $direct['salaire_net'],
            'type_frais_pro' => 'commun',
            'nb_enfants' => 2,
            'conjoint_charge' => true,
        ]);

        $this->assertTrue($resolved['resolution_net']['converge']);
        $this->assertLessThanOrEqual(0.01, $resolved['resolution_net']['ecart']);
        $this->assertEqualsWithDelta(12000, $resolved['input']['salaire_base'], 0.05);
    }

    public function test_net_to_gross_resolution_recovers_salary_with_decimal_cimr_rate(): void
    {
        $input = [
            'salaire_base' => 15000,
            'type_frais_pro' => 'commun',
            'cimr_taux' => 3.5,
        ];
        $direct = $this->calculator->calculer($input);

        $resolved = $this->calculator->resoudreDepuisNet([
            'net_cible' => $direct['salaire_net'],
            'type_frais_pro' => 'commun',
            'cimr_taux' => 3.5,
        ]);

        $this->assertTrue($resolved['resolution_net']['converge']);
        $this->assertLessThanOrEqual(0.01, $resolved['resolution_net']['ecart']);
        $this->assertSame(0.035, $resolved['cimr_taux']);
        $this->assertEqualsWithDelta(15000, $resolved['input']['salaire_base'], 0.05);
    }

    public function test_net_to_gross_resolution_handles_allowance_ceiling_based_on_base_salary(): void
    {
        $input = [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'indemnites' => [['type' => 'representation', 'montant' => 1500]],
        ];
        $direct = $this->calculator->calculer($input);

        $resolved = $this->calculator->resoudreDepuisNet([
            'net_cible' => $direct['salaire_net'],
            'type_frais_pro' => 'commun',
            'indemnites' => [['type' => 'representation', 'montant' => 1500]],
        ]);

        $this->assertTrue($resolved['resolution_net']['converge']);
        $this->assertLessThanOrEqual(0.01, $resolved['resolution_net']['ecart']);
        $this->assertEqualsWithDelta(10000, $resolved['input']['salaire_base'], 0.05);
    }

    public function test_net_to_gross_resolution_rejects_non_positive_target(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calculator->resoudreDepuisNet(['net_cible' => 0]);
    }
    // =========================================================================
    // CIMR
    // =========================================================================

    public function test_cimr_zero_rate_produces_no_contribution(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 10000,
            'cimr_taux' => 0,
            'cimr_taux_employeur' => 0,
        ]);

        $this->assertSame(0.0, $result['cotisation_cimr']);
        $this->assertSame(0.0, $result['cotisation_cimr_patronale']);
    }

    public function test_cimr_shared_splits_between_employee_and_employer(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 10000,
            'cimr_taux' => 3,
            'cimr_taux_employeur' => 6,
        ]);

        $this->assertSame(300.0, $result['cotisation_cimr']);
        $this->assertSame(600.0, $result['cotisation_cimr_patronale']);
    }

    public function test_cimr_employer_cost_includes_employer_cimr(): void
    {
        $without = $this->calculator->calculer([
            'salaire_base' => 10000,
        ]);
        $with = $this->calculator->calculer([
            'salaire_base' => 10000,
            'cimr_taux_employeur' => 6,
        ]);

        $this->assertSame(
            $without['cout_total_employeur'] + 600,
            $with['cout_total_employeur'],
        );
    }

    public function test_cimr_rate_above_max_triggers_warning(): void
    {
        $result = $this->calculator->calculer([
            'salaire_base' => 10000,
            'cimr_taux' => 55,
        ]);

        $this->assertSame(5500.0, $result['cotisation_cimr']);
        $this->assertNotEmpty($result['avertissements']);
    }

    // =========================================================================
    // V1.2 — Avantages CNSS exonérés
    // =========================================================================

    public function test_cnss_exempt_primes_are_excluded_from_cnss_base(): void
    {
        $without = $this->calculator->calculer(['salaire_base' => 5000]);
        $with = $this->calculator->calculer([
            'salaire_base' => 5000,
            'prime_scolarite' => 500,
        ]);

        $this->assertSame($without['cotisation_cnss'], $with['cotisation_cnss']);
        $this->assertSame($without['cotisation_amo'], $with['cotisation_amo']);
        $this->assertSame(5500.0, $with['sbi']);
        $this->assertSame(5000.0, $with['assiette_sociale']);
    }

    public function test_cnss_exempt_primes_are_included_in_ir_base(): void
    {
        $without = $this->calculator->calculer(['salaire_base' => 10000]);
        $with = $this->calculator->calculer([
            'salaire_base' => 10000,
            'prime_aid' => 1000,
        ]);

        $this->assertGreaterThan($without['sbi'], $with['sbi']);
        $this->assertSame(11000.0, $with['sbi']);
    }

    public function test_cnss_exempt_primes_employer_cost_excludes_from_social_charges(): void
    {
        $without = $this->calculator->calculer(['salaire_base' => 5000]);
        $with = $this->calculator->calculer([
            'salaire_base' => 5000,
            'prime_scolarite' => 500,
        ]);

        $this->assertSame($without['cout_cnss_patronal'], $with['cout_cnss_patronal']);
        $this->assertSame($without['cout_amo_patronal'], $with['cout_amo_patronal']);
    }

    // =========================================================================
    // V1.2 — Retraite complémentaire part employeur
    // =========================================================================

    public function test_rc_employer_share_is_added_to_employer_cost(): void
    {
        $without = $this->calculator->calculer(['salaire_base' => 10000]);
        $with = $this->calculator->calculer([
            'salaire_base' => 10000,
            'rc_part_employeur' => 500,
        ]);

        $this->assertSame(
            $without['cout_total_employeur'] + 500,
            $with['cout_total_employeur'],
        );
        $this->assertSame($without['salaire_net'], $with['salaire_net']);
    }
}
