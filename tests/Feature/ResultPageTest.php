<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class ResultPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_result_page_is_split_into_summary_explanation_and_payslip_detail(): void
    {
        $this->post('/calculateur/calculer', [
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
            'personnes_charge' => 2,
        ])->assertOk()
            ->assertSee('Synthèse')
            ->assertSee('Les montants à retenir')
            ->assertSee('Salaire brut')
            ->assertSee('Net à payer')
            ->assertSee('Coût total employeur')
            ->assertSee('MAD/mois')
            ->assertSee('Lecture rapide du net')
            ->assertSee('Salaire brut imposable')
            ->assertSee('Explication pédagogique')
            ->assertSee('Du brut au net, étape par étape')
            ->assertSee('4. Lire le coût employeur séparément')
            ->assertSee('MAD/an')
            ->assertSee('Détail bulletin')
            ->assertSee('Toutes les lignes du calcul')
            ->assertSee('Base / Assiette (MAD/mois)')
            ->assertSee('Voir le détail complet du calcul');
    }
}
