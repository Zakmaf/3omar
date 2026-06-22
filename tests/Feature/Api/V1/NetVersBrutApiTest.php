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
