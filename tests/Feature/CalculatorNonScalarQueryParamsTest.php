<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Régression #152 : profil, s, a et comparer_a sont transmis directement à des
 * services typés ?string. Un paramètre tableau (ex. ?profil[]=x) ne doit jamais
 * provoquer une erreur serveur ; il doit être traité comme absent ou invalide,
 * sans casser le comportement existant pour les chaînes valides.
 */
class CalculatorNonScalarQueryParamsTest extends TestCase
{
    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->codec = app(SimulationCodec::class);
    }

    private function scenario(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'gross_to_net',
            'salaire_base' => 10000,
            'type_frais_pro' => 'commun',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // profil
    // ------------------------------------------------------------------

    public function test_un_profil_sous_forme_de_tableau_ne_provoque_pas_d_erreur_serveur(): void
    {
        $this->get('/calculateur?profil[]=cadre')
            ->assertOk()
            ->assertDontSee('chargé', false);
    }

    public function test_un_profil_valide_continue_de_fonctionner(): void
    {
        $this->get('/calculateur?profil=cadre')
            ->assertOk()
            ->assertSee('Profil « Cadre » chargé', false);
    }

    // ------------------------------------------------------------------
    // s
    // ------------------------------------------------------------------

    public function test_un_payload_s_sous_forme_de_tableau_ne_provoque_pas_d_erreur_serveur(): void
    {
        $this->get('/calculateur?s[]=x')
            ->assertOk()
            ->assertSee('Le lien de simulation est illisible ou expiré');
    }

    public function test_un_payload_s_valide_continue_de_restaurer_la_simulation(): void
    {
        $payload = $this->codec->encode($this->scenario(['salaire_base' => 13500]));

        $this->get('/calculateur?s='.$payload)
            ->assertOk()
            ->assertSee('Simulation reprise depuis le lien partagé');
    }

    // ------------------------------------------------------------------
    // a (page calculateur)
    // ------------------------------------------------------------------

    public function test_un_scenario_memorise_a_sous_forme_de_tableau_ne_provoque_pas_d_erreur_serveur(): void
    {
        $this->get('/calculateur?a[]=x')
            ->assertOk()
            ->assertDontSee('name="comparer_a"', false);
    }

    public function test_un_scenario_memorise_a_valide_continue_de_fonctionner(): void
    {
        $payload = $this->codec->encode($this->scenario());

        $this->get('/calculateur?s='.$payload.'&a='.$payload)
            ->assertOk()
            ->assertSee('Scénario A mémorisé')
            ->assertSee('name="comparer_a"', false);
    }

    // ------------------------------------------------------------------
    // a / b (route comparer)
    // ------------------------------------------------------------------

    public function test_comparer_avec_a_sous_forme_de_tableau_ne_provoque_pas_d_erreur_serveur(): void
    {
        $b = $this->codec->encode($this->scenario());

        $this->get('/calculateur/comparer?a[]=x&b='.$b)
            ->assertRedirect('/calculateur');
    }

    public function test_comparer_avec_b_sous_forme_de_tableau_ne_provoque_pas_d_erreur_serveur(): void
    {
        $a = $this->codec->encode($this->scenario());

        $this->get('/calculateur/comparer?a='.$a.'&b[]=x')
            ->assertRedirect('/calculateur');
    }

    public function test_comparer_avec_des_payloads_valides_continue_de_fonctionner(): void
    {
        $a = $this->codec->encode($this->scenario(['salaire_base' => 10000]));
        $b = $this->codec->encode($this->scenario(['salaire_base' => 12000]));

        $this->get('/calculateur/comparer?a='.$a.'&b='.$b)
            ->assertOk()
            ->assertSee('Scénario A contre scénario B');
    }

    // ------------------------------------------------------------------
    // comparer_a (POST calculer)
    // ------------------------------------------------------------------

    public function test_calculer_avec_comparer_a_sous_forme_de_tableau_ne_provoque_pas_d_erreur_serveur(): void
    {
        $response = $this->post('/calculateur/calculer', $this->scenario([
            'comparer_a' => ['x'],
        ]));

        $response->assertOk();
        $response->assertDontSee('Scénario A contre scénario B');
    }

    public function test_calculer_avec_comparer_a_valide_continue_d_afficher_la_comparaison(): void
    {
        $payload = $this->codec->encode($this->scenario(['salaire_base' => 10000]));

        $this->post('/calculateur/calculer', $this->scenario([
            'salaire_base' => 12000,
            'comparer_a' => $payload,
        ]))->assertOk()->assertSee('Scénario A contre scénario B');
    }
}
