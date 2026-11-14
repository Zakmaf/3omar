<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

/**
 * API publique et frais professionnels du profil 2027 (issue #282).
 *
 * Le profil 2027 reste un projet (PLF n° 60-26, texte déposé, susceptible
 * d'amendements) : il devient calculable, sa provenance reste celle d'un
 * projet, et les deux sens de calcul appliquent les frais professionnels de
 * l'exercice demandé.
 */
class FraisPro2027ApiTest extends TestCase
{
    public function test_brut_vers_net_2027_applique_le_taux_unique_plafonne(): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', ['salaire_base' => 10000, 'type_frais_pro' => 'commun', 'exercice' => 2027])
            ->assertOk()
            ->assertJsonPath('exercice', 2027)
            ->assertJsonPath('reference_fiscale.statut', 'projet')
            ->assertJsonPath('reference_fiscale.validation_legale', null)
            ->assertJsonPath('regime_fp', 'commun_unique')
            ->assertJsonPath('taux_fp', 0.45)
            ->assertJsonPath('plafond_fp', 2916.67)
            ->assertJsonPath('frais_pro', 2916.67)
            ->assertJsonPath('fp_plafonne', true);
    }

    public function test_brut_vers_net_sans_exercice_reste_en_2026(): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', ['salaire_base' => 10000, 'type_frais_pro' => 'commun'])
            ->assertOk()
            ->assertJsonPath('exercice', 2026)
            ->assertJsonPath('regime_fp', 'commun_haut')
            ->assertJsonPath('taux_fp', 0.25)
            ->assertJsonPath('frais_pro', fn ($montant) => (float) $montant === 2500.0);
    }

    public function test_net_vers_brut_2027_suit_le_meme_exercice(): void
    {
        $reponse = $this->postJson('/api/v1/simuler/net-vers-brut', ['net_cible' => 8000, 'type_frais_pro' => 'commun', 'exercice' => 2027])
            ->assertOk()
            ->assertJsonPath('exercice', 2027)
            ->assertJsonPath('regime_fp', 'commun_unique')
            ->assertJsonPath('taux_fp', 0.45)
            ->assertJsonPath('resolution_net.converge', true);

        $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => $reponse->json('input.salaire_base'),
            'type_frais_pro' => 'commun',
            'exercice' => 2027,
        ])->assertOk()->assertJsonPath('salaire_net', $reponse->json('salaire_net'));
    }

    public function test_journaliste_2027_est_calcule_au_regime_general_du_projet(): void
    {
        $this->postJson('/api/v1/simuler/brut-vers-net', ['salaire_base' => 5000, 'type_frais_pro' => 'journaliste', 'exercice' => 2027])
            ->assertOk()
            ->assertJsonPath('regime_fp', 'commun_unique')
            ->assertJsonPath('frais_pro', fn ($montant) => (float) $montant === 2250.0);
    }

    public function test_une_categorie_non_rattachee_au_profil_est_refusee_sur_type_frais_pro(): void
    {
        config(['payroll.exercices.2027.frais_pro.categories' => ['commun', 'journaliste']]);

        foreach ([
            '/api/v1/simuler/brut-vers-net' => ['salaire_base' => 5000],
            '/api/v1/simuler/net-vers-brut' => ['net_cible' => 5000],
        ] as $route => $montant) {
            $response = $this->postJson($route, $montant + ['type_frais_pro' => 'artiste', 'exercice' => 2027]);

            $response->assertStatus(422);
            $this->assertSame(['type_frais_pro'], array_keys($response->json('errors') ?? []), "{$route} : seul type_frais_pro est en erreur");
        }

        // Même catégorie en 2026 : toujours acceptée.
        $this->postJson('/api/v1/simuler/brut-vers-net', ['salaire_base' => 5000, 'type_frais_pro' => 'artiste', 'exercice' => 2026])
            ->assertOk()
            ->assertJsonPath('regime_fp', 'artiste');
    }

    public function test_le_schema_openapi_declare_le_regime_2027(): void
    {
        $spec = json_decode(file_get_contents(public_path('api/docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $regimes = $spec['components']['schemas']['SimulationResult']['properties']['regime_fp']['enum'];

        foreach (['commun_bas', 'commun_haut', 'journaliste', 'artiste', 'commun_unique'] as $regime) {
            $this->assertContains($regime, $regimes);
        }
    }
}
