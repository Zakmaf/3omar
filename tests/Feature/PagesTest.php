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
            ->assertSee('Ton')
            ->assertSee('ligne par ligne.');
        $this->get('/calculateur')->assertOk()->assertSee('Simuler mon bulletin');
        $this->get('/documentation')->assertOk()
            ->assertSee('Documentation des règles 2026')
            ->assertSee('Hypothèses de simulation')
            ->assertDontSee('Taux à jour');
    }

    public function test_result_uses_accurate_non_storage_message(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
        ])->assertOk()
            ->assertSee('Ton bulletin, en clair')
            ->assertSee('Voir le détail complet du calcul')
            ->assertSee("Aucune donnée personnelle n'a été stockée.", false);
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
}
