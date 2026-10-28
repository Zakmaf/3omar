<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

/**
 * Les erreurs 422 de l'API doivent contenir des messages lisibles, en anglais,
 * qui nomment le champ du contrat (issue #187), jamais une cle brute validation.*.
 */
class ApiValidationMessagesTest extends TestCase
{
    public function test_missing_required_fields_are_named_in_english(): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', ['type_frais_pro' => 'commun'])
            ->assertStatus(422)
            ->assertJsonPath('detail', 'The salaire_base field is required.')
            ->assertJsonPath('errors.salaire_base.0', 'The salaire_base field is required.');

        $this->postJson('/api/v1/simuler/net-vers-brut', ['type_frais_pro' => 'commun'])
            ->assertStatus(422)
            ->assertJsonPath('detail', 'The net_cible field is required.');
    }

    public function test_each_rule_returns_a_readable_message(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => 'abc',
            'type_frais_pro' => 'inconnu',
            'cimr_taux' => -1,
            'nb_enfants' => 25,
            'jours_travailles' => 2.5,
            'conjoint_charge' => 'peut-etre',
            'heures_sup' => [['nb_heures' => 5]],
            'indemnites' => [['type' => 'transport', 'montant' => 2000000]],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.salaire_base.0', 'The salaire_base field must be a number.')
            ->assertJsonPath('errors.type_frais_pro.0', 'The selected type_frais_pro is invalid.')
            ->assertJsonPath('errors.cimr_taux.0', 'The cimr_taux field must be at least 0.')
            ->assertJsonPath('errors.nb_enfants.0', 'The nb_enfants field must not be greater than 20.')
            ->assertJsonPath('errors.jours_travailles.0', 'The jours_travailles field must be an integer.')
            ->assertJsonPath('errors.conjoint_charge.0', 'The conjoint_charge field must be true or false.');

        $this->assertStringContainsString('heures_sup.0.type', $response->json('errors')['heures_sup.0.type'][0]);
        $this->assertStringContainsString('indemnites.0.montant', $response->json('errors')['indemnites.0.montant'][0]);
        $this->assertNoRawTranslationKey($response->json());
    }

    public function test_messages_stay_in_english_whatever_the_accept_language(): void
    {
        foreach (['fr', 'ar', 'es', 'en'] as $language) {
            $this->withHeader('Accept-Language', $language)
                ->postJson('/api/v1/simuler/brut-vers-net', ['type_frais_pro' => 'commun'])
                ->assertStatus(422)
                ->assertJsonPath('detail', 'The salaire_base field is required.');
        }
    }

    private function assertNoRawTranslationKey(array $payload): void
    {
        array_walk_recursive($payload, function ($value, $key) {
            if (is_string($value)) {
                $this->assertStringStartsNotWith('validation.', $value, "Cle brute dans {$key}");
            }
        });
    }
}
