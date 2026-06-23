<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class BrutVersNetApiTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
        ], $overrides);
    }

    public function test_valid_post_returns_200_with_expected_keys(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', $this->validPayload());

        $response->assertOk()
            ->assertJsonStructure([
                'sbi',
                'salaire_net',
                'ir_net',
                'cotisation_cnss',
                'cotisation_amo',
                'mode',
                'repartition',
                'avertissements',
            ])
            ->assertJson(['mode' => 'gross_to_net']);
    }

    public function test_missing_salaire_base_returns_422_rfc7807(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'type_frais_pro' => 'commun',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'detail',
                'errors',
            ])
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Unprocessable Content',
                'status' => 422,
            ])
            ->assertJsonValidationErrors('salaire_base');
    }

    public function test_with_all_optional_fields_filled(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'nb_annees_anciennete' => 10,
            'prime_bilan' => 2000,
            'prime_rendement' => 1000,
            'autres_primes' => 500,
            'nb_enfants' => 3,
            'conjoint_charge' => true,
            'cimr_taux' => 6,
            'retraite_complementaire_mensuel' => 500,
            'rc_part_employeur' => 300,
            'mutuelle_salarie' => 200,
            'mutuelle_patronale' => 300,
            'assurance_at_taux' => 1.5,
            'assurance_rc_pro' => 150,
            'retenues_exonerees_ir' => 50,
            'retenues_imposees_ir' => 100,
            'jours_travailles' => 26,
            'heures_sup' => [
                ['type' => 'semaine_diurne', 'nb_heures' => 10],
            ],
            'indemnites' => [
                ['type' => 'transport', 'montant' => 500],
            ],
            'avantages_cnss' => 1700,
        ]);

        $response->assertOk()
            ->assertJson(['mode' => 'gross_to_net'])
            ->assertJsonStructure([
                'sbi',
                'salaire_net',
                'ir_net',
                'prime_anciennete',
                'cotisation_cimr',
                'total_indemnites',
            ]);
    }

    public function test_get_method_returns_405(): void
    {
        $response = $this->getJson('/api/v1/simuler/brut-vers-net');

        $response->assertStatus(405);
    }

    public function test_missing_type_frais_pro_returns_422(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => 5000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type_frais_pro');
    }

    public function test_negative_salaire_base_returns_422(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => -100,
            'type_frais_pro' => 'commun',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('salaire_base');
    }
}
