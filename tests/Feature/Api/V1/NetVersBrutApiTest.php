<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class NetVersBrutApiTest extends TestCase
{
    public function test_valid_post_returns_200_with_resolution_net(): void
    {
        $response = $this->postJson('/api/v1/simuler/net-vers-brut', [
            'net_cible' => 5000,
            'type_frais_pro' => 'commun',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'sbi',
                'salaire_net',
                'ir_net',
                'mode',
                'resolution_net' => [
                    'net_cible',
                    'net_obtenu',
                    'ecart',
                    'iterations',
                    'converge',
                ],
            ])
            ->assertJson(['mode' => 'net_to_gross']);
    }

    /**
     * Net impossible à atteindre (docs/CALCUL.md, issue #240) : l'API répond 200
     * avec le meilleur candidat, converge à false et l'avertissement en anglais,
     * quel que soit l'en-tête Accept-Language.
     */
    public function test_unreachable_target_returns_200_with_unconverged_resolution(): void
    {
        foreach (['en', 'fr'] as $language) {
            $response = $this->withHeader('Accept-Language', $language)->postJson('/api/v1/simuler/net-vers-brut', [
                'net_cible' => 1000,
                'type_frais_pro' => 'commun',
                'retenues_imposees_ir' => 10000000,
            ]);

            $response->assertOk()
                ->assertJsonPath('mode', 'net_to_gross')
                ->assertJsonPath('resolution_net.converge', false);
            $this->assertContains(
                'The target net pay cannot be bracketed within the technical ceiling of the simulator.',
                $response->json('avertissements'),
                $language
            );
        }
    }

    public function test_missing_net_cible_returns_422(): void
    {
        $response = $this->postJson('/api/v1/simuler/net-vers-brut', [
            'type_frais_pro' => 'commun',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unprocessable Content',
                'status' => 422,
            ])
            ->assertJsonValidationErrors('net_cible');
    }

    public function test_zero_net_cible_returns_422(): void
    {
        $response = $this->postJson('/api/v1/simuler/net-vers-brut', [
            'net_cible' => 0,
            'type_frais_pro' => 'commun',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('net_cible');
    }

    public function test_get_method_returns_405(): void
    {
        $response = $this->getJson('/api/v1/simuler/net-vers-brut');

        $response->assertStatus(405);
    }
}
