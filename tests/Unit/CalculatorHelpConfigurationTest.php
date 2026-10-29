<?php

namespace Tests\Unit;

use Tests\TestCase;

class CalculatorHelpConfigurationTest extends TestCase
{
    public function test_no_payroll_rate_or_hours_literal_remains_in_the_calculator_help_keys(): void
    {
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $catalog = require lang_path("{$locale}/ui.php");
            $amo = $catalog['calculator']['amo_override_help'];
            $overtime = $catalog['calculator']['overtime_help'];

            $this->assertDoesNotMatchRegularExpression('/2[,.]26/', $amo, "{$locale} : taux AMO en dur dans amo_override_help");
            $this->assertStringContainsString(':rate', $amo, "{$locale} : paramètre :rate manquant dans amo_override_help");
            $this->assertStringContainsString(':hours', $overtime, "{$locale} : paramètre :hours manquant dans overtime_help");
            $this->assertDoesNotMatchRegularExpression('/191\b/', $overtime, "{$locale} : base horaire en dur dans overtime_help");
        }
    }
}
