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
            ->assertSee('Le bulletin de paie Marocain open source');
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

    public function test_language_switcher_is_hidden_for_v1(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('bi-translate', false)
            ->assertDontSee(route('locale.update', 'en'), false);
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

    public function test_calculator_prioritizes_simple_path_and_exposes_advanced_options(): void
    {
        $this->get('/calculateur')->assertOk()
            ->assertSee('Le salaire de base suffit.')
            ->assertSee('Afficher les options avancées')
            ->assertSee('Simulation pédagogique · environ 2 minutes');
    }

    public function test_locale_switch_persists_and_arabic_enables_rtl(): void
    {
        $this->get('/lang/ar')->assertRedirect();

        $this->get('/')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('كشف الأجر المغربي، مفتوح المصدر');

        $this->get('/calculateur')->assertOk()->assertSee('محاكاة كشف أجري');
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
