<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

/**
 * Nullabilite du contrat d'entree (#284) : sur les deux routes de simulation,
 * un champ facultatif nullable envoye a null explicite est accepte et donne le
 * meme calcul que son omission ; un champ obligatoire a null reste refuse.
 * Les champs patronaux font exception : null y signifie « inconnu ».
 */
class NullabiliteApiTest extends TestCase
{
    private const ROUTES = [
        'gross_to_net' => ['uri' => '/api/v1/simuler/brut-vers-net', 'principal' => ['salaire_base' => 10000]],
        'net_to_gross' => ['uri' => '/api/v1/simuler/net-vers-brut', 'principal' => ['net_cible' => 8000]],
    ];

    /**
     * Champs numeriques, booleens, tableaux et membres numeriques a null.
     * Aucun champ patronal : pour eux, null n'est pas une omission.
     *
     * @return array<string, array<string, mixed>>
     */
    private function variantesNull(): array
    {
        return [
            'scalaires, heures_sup null, indemnites[].montant null' => [
                'nb_annees_anciennete' => null,
                'prime_bilan' => null,
                'nb_enfants' => null,
                'conjoint_charge' => null,
                'cimr_taux' => null,
                'amo_taux_salarie_personnalise' => null,
                'amo_taux_salarie' => null,
                'retenues_imposees_ir' => null,
                'jours_travailles' => null,
                'heures_sup' => null,
                'indemnites' => [['type' => 'transport', 'montant' => null]],
            ],
            'heures_sup[].nb_heures null, indemnites null' => [
                'heures_sup' => [['type' => 'semaine_diurne', 'nb_heures' => null]],
                'indemnites' => null,
            ],
        ];
    }

    public function test_explicit_null_optional_fields_are_accepted_and_match_omission_on_both_routes(): void
    {
        foreach (self::ROUTES as $mode => $route) {
            $base = array_merge($route['principal'], ['type_frais_pro' => 'commun']);

            $omis = $this->postJson($route['uri'], $base);
            $omis->assertOk();

            foreach ($this->variantesNull() as $variante => $nulls) {
                $label = "{$mode} / {$variante}";
                $response = $this->postJson($route['uri'], array_merge($base, $nulls));

                $this->assertSame(200, $response->status(), "{$label} : null explicite refuse, erreurs ".json_encode($response->json('errors')));

                $this->assertSame(
                    [],
                    $this->clesDifferentes($omis->json(), $response->json()),
                    "{$label} : resultat different de l'omission des memes champs."
                );

                $this->assertEquals(0, $response->json('cimr_taux'), "{$label} : cimr_taux par defaut.");
                $this->assertEquals(0, $response->json('cotisation_cimr'), "{$label} : cotisation_cimr par defaut.");
                $this->assertFalse($response->json('amo_taux_salarie_personnalise'), "{$label} : taux AMO legal par defaut.");
                $this->assertEquals(config('payroll.amo.taux'), $response->json('amo_taux_salarie'), "{$label} : taux AMO legal par defaut.");
                $this->assertSame([], $response->json('detail_hs'), "{$label} : aucune heure supplementaire.");
                $this->assertSame([], $response->json('detail_indemnites'), "{$label} : aucune indemnite.");
                $this->assertFalse($response->json('cout_employeur_partiel'), "{$label} : aucun champ patronal inconnu.");
            }
        }
    }

    public function test_explicit_null_employer_field_is_accepted_and_flagged_unknown(): void
    {
        foreach (self::ROUTES as $mode => $route) {
            $response = $this->postJson($route['uri'], array_merge($route['principal'], [
                'type_frais_pro' => 'commun',
                'mutuelle_patronale' => null,
            ]));

            $this->assertSame(200, $response->status(), "{$mode} : mutuelle_patronale null refuse.");
            $this->assertTrue($response->json('mutuelle_patronale_inconnue'), "{$mode} : null patronal = inconnu.");
            $this->assertTrue($response->json('cout_employeur_partiel'), "{$mode} : cout employeur partiel.");
        }
    }

    public function test_required_fields_set_to_null_are_rejected_on_both_routes(): void
    {
        foreach (self::ROUTES as $mode => $route) {
            $principal = array_key_first($route['principal']);

            $this->postJson($route['uri'], [$principal => null, 'type_frais_pro' => 'commun'])
                ->assertStatus(422)
                ->assertJsonValidationErrors($principal);

            $this->postJson($route['uri'], array_merge($route['principal'], ['type_frais_pro' => null]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('type_frais_pro');
        }
    }

    public function test_null_array_member_type_is_rejected_when_its_value_is_given(): void
    {
        foreach (self::ROUTES as $mode => $route) {
            $base = array_merge($route['principal'], ['type_frais_pro' => 'commun']);

            $this->postJson($route['uri'], array_merge($base, [
                'heures_sup' => [['type' => null, 'nb_heures' => 4]],
            ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('heures_sup.0.type');

            $this->postJson($route['uri'], array_merge($base, [
                'indemnites' => [['type' => null, 'montant' => 100]],
            ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('indemnites.0.type');
        }
    }

    /**
     * Cles de premier niveau dont la valeur differe, hors `input` (echo des
     * entrees, qui contient forcement les null envoyes). Garde un message
     * d'echec court au lieu d'imprimer toute la reponse.
     *
     * @return list<string>
     */
    private function clesDifferentes(array $attendu, array $obtenu): array
    {
        unset($attendu['input'], $obtenu['input']);

        $cles = array_unique(array_merge(array_keys($attendu), array_keys($obtenu)));
        $differentes = array_filter(
            $cles,
            fn (string $cle) => ! array_key_exists($cle, $attendu)
                || ! array_key_exists($cle, $obtenu)
                || $attendu[$cle] !== $obtenu[$cle],
        );
        sort($differentes);

        return array_values($differentes);
    }
}
