<?php

namespace Tests\Feature;

use Tests\TestCase;

class CalculatorHelpRendersConfigurationTest extends TestCase
{
    public function test_calculator_help_texts_follow_the_payroll_configuration_in_every_locale(): void
    {
        config(['payroll.amo.taux' => 0.03, 'payroll.smig.heures_legales' => 190]);

        $expectedRates = [
            'fr' => '3,00',
            'en' => '3.00',
            'es' => '3,00',
            'ar' => '3.00',
        ];
        $expectedHours = [
            'fr' => '/ 190 h',
            'en' => '/ 190 h',
            'es' => '/ 190 h',
            'ar' => '/ 190 ساعة',
        ];

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $response = $this->withSession(['locale' => $locale])->get('/calculateur');

            $response->assertOk();
            $response->assertSee($expectedRates[$locale].'% (', false);
            $response->assertSee($expectedHours[$locale], false);
        }
    }

    public function test_calculator_help_texts_are_unchanged_at_default_configuration(): void
    {
        $expected = [
            'fr' => 'taux légal AMO salarié de 2,26% (Loi 65-00)',
            'en' => 'legal employee AMO rate of 2.26% (Law 65-00)',
            'es' => 'tasa legal AMO del empleado del 2,26% (Ley 65-00)',
            'ar' => 'النسبة القانونية لـ AMO الخاصة بالأجير وهي 2.26% (القانون 65-00)',
        ];
        $expectedHours = [
            'fr' => 'Salaire base / 191 h',
            'en' => 'Base salary / 191 h',
            'es' => 'Salario base / 191 h',
            'ar' => 'الأجر الأساسي / 191 ساعة',
        ];

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            $response = $this->withSession(['locale' => $locale])->get('/calculateur');

            $response->assertOk();
            $response->assertSee($expected[$locale], false);
            $response->assertSee($expectedHours[$locale], false);
        }
    }
}
