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
            'cimr_actif' => true,
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
}
