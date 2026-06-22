<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ParametresApiTest extends TestCase
{
    public function test_get_returns_200_with_payroll_structure(): void
    {
        $response = $this->getJson('/api/v1/parametres');

        $response->assertOk()
            ->assertJsonStructure([
                'cnss' => ['taux', 'plafond'],
                'amo' => ['taux'],
                'ir' => ['baremes'],
            ]);
    }

    public function test_values_match_config(): void
    {
        $response = $this->getJson('/api/v1/parametres');

        $response->assertOk()
            ->assertJson([
                'cnss' => [
                    'taux' => config('payroll.cnss.taux'),
                    'plafond' => config('payroll.cnss.plafond'),
                ],
                'amo' => [
                    'taux' => config('payroll.amo.taux'),
                ],
                'year' => config('payroll.year'),
            ]);
    }

    public function test_post_method_returns_405(): void
    {
        $response = $this->postJson('/api/v1/parametres');

        $response->assertStatus(405);
    }
}
