<?php

namespace Tests\Feature\Api\V1;

use PHPUnit\Framework\Attributes\DataProvider;
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
     * Les cinq champs patronaux pouvant etre inconnus (nom canonique du champ
     * d'entree) et le drapeau deja expose par le moteur pour chacun.
     */
    private const DRAPEAUX_PATRONAUX = [
        'cimr_taux_employeur' => 'cimr_taux_employeur_inconnu',
        'rc_part_employeur' => 'rc_part_employeur_inconnu',
        'mutuelle_patronale' => 'mutuelle_patronale_inconnue',
        'assurance_at_taux' => 'assurance_at_inconnue',
        'assurance_rc_pro' => 'assurance_rc_pro_inconnue',
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
     * Cas distincts du contrat `champs_employeur_inconnus` (#208), chacun joue
     * sur les deux routes. Les cinq noms canoniques sont exerces au total :
     * un inconnu seul a cote de valeurs connues, quatre inconnus a cote d'une
     * valeur connue, zero explicite partout, omission de tous les champs.
     * Montants synthetiques, sans rapport avec un bulletin reel.
     *
     * @return iterable<string, array{string, array<string, mixed>, list<string>}>
     */
    public static function inconnusPatronaux(): iterable
    {
        $cas = [
            'un inconnu, autres connus' => [
                ['mutuelle_patronale' => null, 'rc_part_employeur' => 500, 'assurance_rc_pro' => 100],
                ['mutuelle_patronale'],
            ],
            'quatre inconnus, mutuelle connue' => [
                [
                    'cimr_taux' => 6,
                    'cimr_taux_employeur' => null,
                    'rc_part_employeur' => null,
                    'assurance_at_taux' => null,
                    'assurance_rc_pro' => null,
                    'mutuelle_patronale' => 300,
                ],
                ['cimr_taux_employeur', 'rc_part_employeur', 'assurance_at_taux', 'assurance_rc_pro'],
            ],
            'zero explicite' => [
                array_fill_keys(array_keys(self::DRAPEAUX_PATRONAUX), 0),
                [],
            ],
            'omission' => [[], []],
        ];

        foreach (array_keys(self::ROUTES) as $mode) {
            foreach ($cas as $nom => [$champs, $attendus]) {
                yield "{$mode} / {$nom}" => [$mode, $champs, $attendus];
            }
        }
    }

    /**
     * Contrat strictement additif : les drapeaux, `cout_employeur_partiel`,
     * `avertissements` et les montants restent ceux d'avant ; la nouvelle cle
     * liste, sans ordre garanti ni doublon, les champs d'entree envoyes a null.
     */
    #[DataProvider('inconnusPatronaux')]
    public function test_unknown_employer_fields_are_listed_by_canonical_input_name(string $mode, array $champs, array $attendus): void
    {
        $route = self::ROUTES[$mode];
        $payload = array_merge($route['principal'], ['type_frais_pro' => 'commun'], $champs);

        $response = $this->postJson($route['uri'], $payload);
        $this->assertSame(200, $response->status(), "{$mode} : requete refusee, erreurs ".json_encode($response->json('errors')));

        // 1. Contrat existant, verifie avant la nouvelle cle.
        foreach (self::DRAPEAUX_PATRONAUX as $champ => $drapeau) {
            $this->assertSame(
                in_array($champ, $attendus, true),
                $response->json($drapeau),
                "{$mode} : drapeau {$drapeau} pour {$champ}."
            );
        }

        $partiel = $attendus !== [];
        $this->assertSame($partiel, $response->json('cout_employeur_partiel'), "{$mode} : cout_employeur_partiel.");

        $avertissements = $response->json('avertissements');
        $this->assertIsArray($avertissements, "{$mode} : avertissements reste une liste.");
        $messagePartiel = __('ui.result.warning_partial_employer_cost');
        $this->assertSame(
            $partiel,
            in_array($messagePartiel, $avertissements, true),
            "{$mode} : le message de cout partiel reste dans avertissements si et seulement si un champ est inconnu."
        );

        // Montants : un champ inconnu compte pour zero, comme avant. On compare a
        // la meme requete ou les null deviennent 0, sans recalculer aucun taux.
        $nuls = array_keys(array_filter($champs, fn ($valeur) => $valeur === null));
        if ($nuls !== []) {
            $zero = $this->postJson($route['uri'], array_merge($payload, array_fill_keys($nuls, 0)));
            $zero->assertOk();

            $differencesAdmises = array_merge(
                array_map(fn (string $champ) => self::DRAPEAUX_PATRONAUX[$champ], $nuls),
                ['cout_employeur_partiel', 'avertissements', 'champs_employeur_inconnus'],
            );
            $this->assertSame(
                [],
                array_values(array_diff($this->clesDifferentes($zero->json(), $response->json()), $differencesAdmises)),
                "{$mode} : montants ou cles differents de la meme saisie a zero."
            );
            $this->assertSame(
                $zero->json('avertissements'),
                array_values(array_diff($avertissements, [$messagePartiel])),
                "{$mode} : avertissements inchanges hors message de cout partiel."
            );
        }

        // 2. Nouvelle cle, presente dans les deux modes.
        $this->assertArrayHasKey('champs_employeur_inconnus', $response->json(), "{$mode} : champs_employeur_inconnus absent.");
        $liste = $response->json('champs_employeur_inconnus');
        $this->assertIsArray($liste, "{$mode} : champs_employeur_inconnus doit etre une liste.");
        $this->assertTrue(array_is_list($liste), "{$mode} : champs_employeur_inconnus doit etre une liste JSON, pas un objet.");
        $this->assertSame(count($liste), count(array_unique($liste)), "{$mode} : doublon dans champs_employeur_inconnus.");
        $this->assertEqualsCanonicalizing($attendus, $liste, "{$mode} : noms canoniques des champs inconnus.");
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
