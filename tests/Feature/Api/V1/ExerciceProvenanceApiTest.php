<?php

namespace Tests\Feature\Api\V1;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Provenance fiscale d'un résultat de simulation (issue #281).
 *
 * Chaque résultat dit quel exercice fiscal a réellement servi au calcul et
 * de quelle référence il provient : `exercice` (entier) et `reference_fiscale`
 * (métadonnées du profil config('payroll.exercices.*') : statut, source,
 * reference_ir, periode, date_effet_projetee, validation_legale). Cette
 * provenance est distincte du numéro de version applicatif et n'embarque ni
 * le barème ni les frais professionnels.
 *
 * Les valeurs attendues sont lues dans le profil, jamais recopiées.
 */
class ExerciceProvenanceApiTest extends TestCase
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
    public function test_le_resultat_expose_l_exercice_et_la_reference_fiscale_utilises(string $route, array $requete): void
    {
        $profil = config('payroll.exercices.2026');

        $response = $this->postJson($route, $requete + ['exercice' => 2026]);

        $response->assertOk()
            ->assertJsonPath('exercice', 2026)
            ->assertJsonPath('reference_fiscale.statut', 'en_vigueur')
            ->assertJsonPath('reference_fiscale.source', $profil['source'])
            ->assertJsonPath('reference_fiscale.reference_ir', $profil['reference_ir'])
            ->assertJsonPath('reference_fiscale.periode', $profil['periode'])
            ->assertJsonPath('reference_fiscale.date_effet_projetee', $profil['date_effet_projetee'])
            ->assertJsonPath('reference_fiscale.validation_legale', $profil['validation_legale'])
            ->assertJsonMissingPath('reference_fiscale.ir_baremes')
            ->assertJsonMissingPath('reference_fiscale.frais_pro');
    }
}
