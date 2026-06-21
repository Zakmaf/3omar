<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_calculator_is_rate_limited(): void
    {
        $payload = ['salaire_base' => 5000, 'type_frais_pro' => 'commun'];

        for ($i = 0; $i < 30; $i++) {
            $this->post('/calculateur/calculer', $payload)->assertOk();
        }

        $this->post('/calculateur/calculer', $payload)->assertStatus(429);
    }

    public function test_heures_sup_array_is_bounded(): void
    {
        $heures = array_fill(0, 11, ['type' => 'semaine_diurne', 'nb_heures' => 2]);

        $this->from('/calculateur')->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
            'heures_sup' => $heures,
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('heures_sup');
    }

    public function test_indemnites_array_is_bounded(): void
    {
        $indemnites = array_fill(0, 11, ['type' => 'transport', 'montant' => 100]);

        $this->from('/calculateur')->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
            'indemnites' => $indemnites,
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('indemnites');
    }

    public function test_salary_rejects_absurdly_large_values(): void
    {
        $this->from('/calculateur')->post('/calculateur/calculer', [
            'salaire_base' => 99999999,
            'type_frais_pro' => 'commun',
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('salaire_base');
    }

    public function test_net_cible_rejects_absurdly_large_values(): void
    {
        $this->from('/calculateur')->post('/calculateur/calculer', [
            'mode' => 'net_to_gross',
            'net_cible' => 99999999,
            'type_frais_pro' => 'commun',
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('net_cible');
    }

    public function test_heures_sup_nb_heures_is_bounded(): void
    {
        $this->from('/calculateur')->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
            'heures_sup' => [['type' => 'semaine_diurne', 'nb_heures' => 9999]],
        ])->assertRedirect('/calculateur')
            ->assertSessionHasErrors('heures_sup.0.nb_heures');
    }

    public function test_valid_payload_within_bounds_is_accepted(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
            'heures_sup' => [['type' => 'semaine_diurne', 'nb_heures' => 10]],
            'indemnites' => [['type' => 'transport', 'montant' => 500]],
        ])->assertOk();
    }
}
