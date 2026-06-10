<?php

namespace Tests\Unit;

use Tests\TestCase;

class PayrollConfigurationTest extends TestCase
{
    public function test_smig_monthly_value_matches_hourly_basis(): void
    {
        $smig = config('payroll.smig');

        $this->assertSame($smig['mensuel'], round($smig['horaire'] * $smig['heures_legales'], 2));
    }

    public function test_ir_brackets_are_ordered_without_integer_gaps(): void
    {
        $brackets = config('payroll.ir.baremes');

        foreach ($brackets as $index => $bracket) {
            if ($index === 0) {
                $this->assertSame(0, $bracket['min']);

                continue;
            }

            $this->assertSame($brackets[$index - 1]['max'] + 1, $bracket['min']);
        }

        $this->assertNull($brackets[array_key_last($brackets)]['max']);
    }

    public function test_overtime_labels_match_configured_rates(): void
    {
        $this->assertSame(
            array_keys(config('payroll.heures_sup.majorations')),
            array_keys(config('payroll.heures_sup.labels')),
        );
    }

    public function test_family_ceiling_is_a_multiple_of_per_person_amount(): void
    {
        $family = config('payroll.charges_famille');

        $this->assertSame(0.0, fmod($family['plafond'], $family['par_personne']));
    }
}
