<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Régression #153 : les bannières de préremplissage (profil chargé, simulation
 * restaurée, lien invalide) doivent refléter le chemin de préremplissage
 * réellement sélectionné et effectivement injecté dans le formulaire.
 */
class CalculatorPrefillBannersTest extends TestCase
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

    public function test_un_profil_prioritaire_sur_un_lien_valide_n_affiche_pas_l_avertissement_de_lien_invalide(): void
    {
        $payload = $this->codec->encode($this->scenario(['salaire_base' => 13500]));

        $response = $this->get('/calculateur?profil=cadre&s='.$payload)->assertOk();

        $response->assertSee('Profil « Cadre » chargé', false);
        $response->assertDontSee('Le lien de simulation est illisible ou expiré');
    }

    public function test_une_erreur_de_validation_avec_profil_dans_l_url_n_affiche_pas_la_bannière_de_profil_charge(): void
    {
        $response = $this->followingRedirects()
            ->from('/calculateur?profil=cadre')
            ->post('/calculateur/calculer', ['salaire_base' => '', 'type_frais_pro' => 'commun'])
            ->assertOk();

        $response->assertDontSee('Profil « Cadre » chargé', false);
    }

    public function test_une_erreur_de_validation_avec_lien_partage_dans_l_url_n_affiche_pas_la_bannière_de_restauration(): void
    {
        $payload = $this->codec->encode($this->scenario(['salaire_base' => 13500]));

        $response = $this->followingRedirects()
            ->from('/calculateur?s='.$payload)
            ->post('/calculateur/calculer', ['salaire_base' => '', 'type_frais_pro' => 'commun'])
            ->assertOk();

        $response->assertDontSee('Simulation reprise depuis le lien partagé');
    }

    public function test_une_erreur_de_validation_avec_un_lien_partage_reellement_illisible_conserve_l_avertissement(): void
    {
        // Ici le lien « s » n'est pas seulement ignoré par priorité (comme dans le
        // profil ou le lien valide ci-dessus) : il est réellement illisible. La
        // bannière d'avertissement doit donc rester affichée même si une erreur de
        // validation a par ailleurs rempli la session avec l'ancienne saisie.
        $response = $this->followingRedirects()
            ->from('/calculateur?s=payload-casse')
            ->post('/calculateur/calculer', ['salaire_base' => '', 'type_frais_pro' => 'commun'])
            ->assertOk();

        $response->assertSee('Le salaire de base est obligatoire.');
        $response->assertSee('Le lien de simulation est illisible ou expiré');
        $response->assertDontSee('Simulation reprise depuis le lien partagé');
    }

    public function test_une_erreur_de_validation_avec_un_lien_partage_valide_n_affiche_pas_la_bannière_de_lien_invalide(): void
    {
        // Le lien « s » est ici parfaitement valide : il est seulement déprioritisé
        // au profit de l'ancienne saisie (retour de validation). Le remettre à
        // « non restauré » pour masquer la bannière de restauration ne doit pas,
        // par effet de bord, faire apparaître la bannière « lien invalide », qui
        // serait tout aussi fausse que la bannière de restauration.
        $payload = $this->codec->encode($this->scenario(['salaire_base' => 13500]));

        $response = $this->followingRedirects()
            ->from('/calculateur?s='.$payload)
            ->post('/calculateur/calculer', ['salaire_base' => '', 'type_frais_pro' => 'commun'])
            ->assertOk();

        $response->assertDontSee('Le lien de simulation est illisible ou expiré');
    }

    public function test_un_profil_prioritaire_sur_un_lien_reellement_illisible_n_affiche_pas_l_avertissement_de_lien_invalide(): void
    {
        // Ici « s » n'est pas seulement déprioritisé (comme dans le premier test
        // de cette classe) : il est réellement illisible. Le profil gagnant la
        // priorité, la branche « s » n'est jamais évaluée : l'avertissement de
        // lien invalide doit donc rester masqué, comme lorsque « s » est valide.
        $response = $this->get('/calculateur?profil=cadre&s=payload-casse')->assertOk();

        $response->assertSee('Profil « Cadre » chargé', false);
        $response->assertDontSee('Le lien de simulation est illisible ou expiré');
    }
}
