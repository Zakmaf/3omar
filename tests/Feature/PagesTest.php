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
        $this->get('/documentation')->assertOk()->assertSee('Documentation légale 2026');
    }

    public function test_result_uses_accurate_non_storage_message(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
        ])->assertOk()
            ->assertSee('Ton bulletin simulé est prêt.')
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
}
