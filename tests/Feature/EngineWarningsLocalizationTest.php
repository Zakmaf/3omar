<?php

namespace Tests\Feature;

use App\Services\PayrollCalculatorService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Avertissements du moteur (issue #207) : chaque message passe par une clé
 * lang/{fr,en,ar,es}/ui.php, suit la langue active et ne cite jamais une
 * référence interne au dépôt (issue, PR).
 */
class EngineWarningsLocalizationTest extends TestCase
{
    /**
     * Une saisie qui déclenche tous les avertissements de calculer() : indemnité
     * au-delà du plafond, AMO personnalisée, base sous le SMIG, deux taux CIMR
     * au-delà du plafond, personnes à charge, épargne retraite au-delà du
     * plafond de déduction et coût employeur partiel.
     */
    private const SAISIE_TOUS_AVERTISSEMENTS = [
        'salaire_base' => 3000,
        'type_frais_pro' => 'commun',
        'amo_taux_salarie_personnalise' => 1,
        'amo_taux_salarie' => 0,
        'cimr_taux' => 60,
        'cimr_taux_employeur' => 55,
        'nb_enfants' => 7,
        'conjoint_charge' => 1,
        'retraite_complementaire_mensuel' => 2000,
        'mutuelle_patronale_inconnu' => 1,
        'indemnites' => [['type' => 'transport', 'montant' => 800]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /**
     * @return list<string>
     */
    private function avertissements(string $locale, array $saisie = self::SAISIE_TOUS_AVERTISSEMENTS): array
    {
        app()->setLocale($locale);

        return app(PayrollCalculatorService::class)->calculer($saisie)['avertissements'];
    }

    private function locales(): array
    {
        return array_keys(config('app.supported_locales'));
    }

    public function test_no_engine_warning_cites_an_internal_reference(): void
    {
        $saisieNetDejaAtteint = [
            'net_cible' => 100,
            'type_frais_pro' => 'commun',
            'indemnites' => [['type' => 'transport', 'montant' => 500]],
        ];

        foreach ($this->locales() as $locale) {
            $avertissements = $this->avertissements($locale);
            $this->assertGreaterThanOrEqual(8, count($avertissements), $locale);

            app()->setLocale($locale);
            $avertissements = array_merge(
                $avertissements,
                app(PayrollCalculatorService::class)->resoudreDepuisNet($saisieNetDejaAtteint)['avertissements'],
            );

            foreach ($avertissements as $avertissement) {
                $this->assertDoesNotMatchRegularExpression('/#\s*\d+/', $avertissement, $locale);
                $this->assertStringNotContainsStringIgnoringCase('issue', $avertissement, $locale);
                $this->assertStringNotContainsString('ui.result.', $avertissement, $locale);
            }
        }
    }

    public function test_every_engine_warning_follows_the_active_locale(): void
    {
        $francais = $this->avertissements('fr');

        foreach (['en', 'es', 'ar'] as $locale) {
            $traduits = $this->avertissements($locale);
            $this->assertCount(count($francais), $traduits, $locale);

            foreach ($traduits as $i => $avertissement) {
                $this->assertNotSame($francais[$i], $avertissement, "{$locale} : avertissement {$i} resté en français.");
            }
        }
    }

    public function test_warning_parameters_are_formatted_once_and_rates_come_from_the_configuration(): void
    {
        $tauxLegal = str_replace('.', ',', (string) round(config('payroll.amo.taux') * 100, 2));
        $plafondCimr = (string) round(config('payroll.cimr.taux_max') * 100, 2);
        $smig = number_format(config('payroll.smig.mensuel'), 2, ',', ' ');

        foreach ($this->locales() as $locale) {
            $avertissements = $this->avertissements($locale);

            $this->assertContains(trans('ui.result.warning_amo_override', ['saisi' => '0', 'legal' => $tauxLegal], $locale), $avertissements, $locale);
            $this->assertContains(trans('ui.result.warning_below_smig', ['salaire' => '3 000,00', 'smig' => $smig], $locale), $avertissements, $locale);
            $this->assertContains(trans('ui.result.warning_cimr_employee_cap', ['taux' => '60', 'plafond' => $plafondCimr], $locale), $avertissements, $locale);
            $this->assertContains(trans('ui.result.warning_cimr_employer_cap', ['taux' => '55', 'plafond' => $plafondCimr], $locale), $avertissements, $locale);
            $this->assertContains(trans('ui.result.warning_partial_employer_cost', [], $locale), $avertissements, $locale);
        }
    }

    public function test_net_to_gross_warnings_are_translated(): void
    {
        foreach ($this->locales() as $locale) {
            app()->setLocale($locale);
            $resultat = app(PayrollCalculatorService::class)->resoudreDepuisNet([
                'net_cible' => 100,
                'type_frais_pro' => 'commun',
                'indemnites' => [['type' => 'transport', 'montant' => 500]],
            ]);

            $this->assertContains(trans('ui.result.warning_net_already_reached', [], $locale), $resultat['avertissements'], $locale);
        }
    }

    public function test_the_public_api_returns_engine_warnings_in_english(): void
    {
        $avertissements = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'amo_taux_salarie_personnalise' => true,
            'amo_taux_salarie' => 0,
        ])->assertOk()->json('avertissements');

        $tauxLegal = str_replace('.', ',', (string) round(config('payroll.amo.taux') * 100, 2));

        $this->assertContains(trans('ui.result.warning_amo_override', ['saisi' => '0', 'legal' => $tauxLegal], 'en'), $avertissements);
        $this->assertNotContains(trans('ui.result.warning_amo_override', ['saisi' => '0', 'legal' => $tauxLegal], 'fr'), $avertissements);
    }

    public function test_result_page_shows_engine_warnings_translated_and_right_to_left_in_arabic(): void
    {
        $smig = number_format(config('payroll.smig.mensuel'), 2, ',', ' ');

        $this->withSession(['locale' => 'ar'])
            ->post('/calculateur/calculer', ['salaire_base' => 3000, 'type_frais_pro' => 'commun'])
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee(trans('ui.result.warning_below_smig', ['salaire' => '3 000,00', 'smig' => $smig], 'ar'))
            ->assertDontSee('inférieur au SMIG');
    }
}
