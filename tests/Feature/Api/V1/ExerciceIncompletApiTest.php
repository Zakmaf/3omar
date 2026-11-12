<?php

namespace Tests\Feature\Api\V1;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un exercice fiscal explicite est une vraie entrée de l'API (issue #281).
 *
 * Les profils prospectifs (2027 à 2031, PLF n° 60-26 non promulgué) n'ont pas
 * encore leurs frais professionnels (`frais_pro` null, ticket #282). Un calcul
 * complet demandé pour un tel exercice doit être refusé sur le champ
 * `exercice`, jamais servi avec les frais professionnels 2026 ni en ignorant
 * silencieusement l'exercice demandé.
 *
 * Les requêtes sont par ailleurs valides : sans `exercice`, elles reçoivent un
 * 200 calculé en 2026. Seul le champ `exercice` doit donc être en erreur.
 */
class ExerciceIncompletApiTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function modes(): array
    {
        return [
            'brut vers net' => ['/api/v1/simuler/brut-vers-net', ['salaire_base' => 20000, 'type_frais_pro' => 'commun']],
            'net vers brut' => ['/api/v1/simuler/net-vers-brut', ['net_cible' => 15000, 'type_frais_pro' => 'commun']],
        ];
    }

    /**
     * @param  array<string, mixed>  $requete
     */
    #[DataProvider('modes')]
    public function test_exercice_sans_frais_professionnels_parametres_est_refuse(string $route, array $requete): void
    {
        $this->assertNull(
            config('payroll.exercices.2027.frais_pro'),
            'Prémisse : les frais professionnels du profil 2027 ne sont pas encore paramétrés (#282).',
        );

        $response = $this->postJson($route, $requete + ['exercice' => 2027]);

        $response->assertStatus(422);
        $this->assertSame(
            ['exercice'],
            array_keys($response->json('errors') ?? []),
            'Seul le champ exercice est en erreur : le reste de la requête est valide.',
        );
    }
}
