<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class PagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_public_pages_are_available(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Le bulletin de paie Marocain open source')
            ->assertSee('Version '.config('app.version'));
        $this->get('/calculateur')->assertOk()->assertSee('Simuler mon bulletin');
        $this->get('/documentation')->assertOk()
            ->assertSee('Documentation des règles')
            ->assertSee('Hypothèses de simulation')
            ->assertDontSee('Taux à jour');
    }

    public function test_adsense_is_not_rendered_outside_production(): void
    {
        config()->set('ads.enabled', true);
        config()->set('ads.client', 'ca-pub-test');
        config()->set('ads.placements.header.slot', 'header-test');
        config()->set('ads.placements.footer.slot', 'footer-test');

        foreach (['/', '/calculateur', '/documentation'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('pagead2.googlesyndication.com', false)
                ->assertDontSee('ca-pub-test', false);
        }
    }

    public function test_language_switcher_is_visible_for_v11(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('bi-translate', false)
            ->assertSee(route('locale.update', 'en'), false)
            ->assertSee(route('locale.update', 'ar'), false)
            ->assertSee('title="Français"', false)
            ->assertSee('title="English"', false)
            ->assertSee('title="العربية"', false)
            ->assertSee('title="Español"', false);
    }

    public function test_result_uses_accurate_non_storage_message(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
        ])->assertOk()
            ->assertSee('Votre bulletin, en clair')
            ->assertSee('Voir le détail complet du calcul')
            ->assertSeeText("Aucune donnée personnelle n'est stockée.");
    }

    public function test_direct_result_url_redirects_to_calculator_with_friendly_notice(): void
    {
        $this->get('/calculateur/calculer')
            ->assertRedirect('/calculateur');

        $this->followRedirects($this->get('/calculateur/calculer'))
            ->assertOk()
            ->assertSee('Aucun résultat à afficher pour le moment');
    }

    public function test_unknown_allowance_type_is_rejected(): void
    {
        $this->from('/calculateur')->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
            'indemnites' => [['type' => 'inconnue', 'montant' => 100]],
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('indemnites.0.type');
    }

    public function test_duplicate_allowance_type_is_rejected(): void
    {
        $this->from('/calculateur')->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
            'indemnites' => [
                ['type' => 'transport', 'montant' => 300],
                ['type' => 'transport', 'montant' => 200],
            ],
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('indemnites.0.type');
    }

    public function test_calculator_has_nine_step_sections_with_auto_numbering(): void
    {
        $response = $this->get('/calculateur')->assertOk();
        $html = $response->getContent();

        preg_match_all('/<details[^>]*data-step-section/', $html, $matches);
        $this->assertCount(9, $matches[0], "Le formulaire doit contenir exactement 9 sections d'étape");

        $this->assertStringContainsString('class="step-label"', $html);
        $this->assertStringContainsString('counter-reset: step', $html);
    }

    public function test_calculator_prioritizes_simple_path_and_exposes_advanced_options(): void
    {
        $this->get('/calculateur')->assertOk()
            ->assertSee('Simulation guidée étape par étape')
            ->assertSee('Je connais le net')
            ->assertSee('Lancer le calcul du bulletin')
            ->assertSee('Un salaire de départ suffit pour lancer une simulation')
            ->assertSee('quick-submit-panel', false)
            ->assertSee('Passer cette rubrique')
            ->assertSee('Simulation pédagogique · environ 2 minutes');
    }

    public function test_calculator_uses_constrained_inputs_for_common_bounded_choices(): void
    {
        $this->get('/calculateur')->assertOk()
            ->assertSee('Tranche d', false)
            ->assertSee('name="nb_annees_anciennete"', false)
            ->assertSee('2 à 4 ans · 5%')
            ->assertSee('Primes imposables')
            ->assertSee('name="prime_bilan" value="0"', false)
            ->assertSee('name="prime_rendement" value="0"', false)
            ->assertSee('name="cimr_repartition" value="partage"', false)
            ->assertSee('Taux CIMR salarié')
            ->assertSee('Taux CIMR employeur')
            ->assertDontSee('for="cimrRepSalarie"', false)
            ->assertSee('name="nb_enfants"', false)
            ->assertSee('data-max-personnes="6"', false)
            ->assertSee('6 ou plus')
            ->assertSee('Conjoint à charge')
            ->assertSee('Déduction fiscale simulée');
    }

    public function test_net_to_gross_mode_returns_reconstructed_salary_and_employer_cost(): void
    {
        $this->post('/calculateur/calculer', [
            'mode' => 'net_to_gross',
            'net_cible' => 8000,
            'type_frais_pro' => 'commun',
        ])->assertOk()
            ->assertSee('Reconstitution depuis le net')
            ->assertSee('Base reconstituée')
            ->assertSee('Coût total employeur');
    }

    public function test_net_to_gross_mode_requires_target_net(): void
    {
        $this->from('/calculateur')->post('/calculateur/calculer', [
            'mode' => 'net_to_gross',
            'type_frais_pro' => 'commun',
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors(['net_cible' => 'Le net à payer cible est obligatoire.']);
    }

    public function test_locale_switch_persists_and_arabic_enables_rtl(): void
    {
        $this->get('/lang/ar')->assertRedirect('/');

        $this->withHeader('referer', url('/documentation'))
            ->get('/lang/ar')
            ->assertRedirect('/documentation');

        $this->get('/')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('كشف الأجر المغربي، مفتوح المصدر');

        $this->get('/calculateur')->assertOk()
            ->assertSee('محاكاة كشف أجري')
            ->assertSee('aria-current="true"', false);
    }

    public function test_supported_latin_locales_render_translated_navigation(): void
    {
        $this->withSession(['locale' => 'en'])->get('/')->assertOk()->assertSee('Simulate my payslip');
        $this->withSession(['locale' => 'es'])->get('/')->assertOk()->assertSee('Simular mi nómina');
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->get('/lang/de')->assertNotFound();
    }

    public function test_validation_messages_use_active_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->from('/calculateur')
            ->post('/calculateur/calculer', ['type_frais_pro' => 'commun'])
            ->assertRedirect('/calculateur')
            ->assertSessionHasErrors(['salaire_base' => 'The base salary is required.']);
    }
}
