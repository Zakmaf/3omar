<?php

namespace Tests\Feature;

use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Parcours profils prêts à l'emploi (#51), reprise/partage par URL (#50)
 * et comparaison de deux scénarios (#47).
 */
class SimulationSharingTest extends TestCase
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
    // Profils prêts à l'emploi (#51)
    // ------------------------------------------------------------------

    public function test_la_page_calculateur_propose_les_profils(): void
    {
        $this->get('/calculateur')
            ->assertOk()
            ->assertSee("Partir d'un profil type")
            ->assertSee('SMIG')
            ->assertSee('Salarié standard')
            ->assertSee('Cadre')
            ->assertSee('Avec primes')
            ->assertSee('Avec CIMR')
            ->assertSee('Journaliste');
    }

    public function test_charger_un_profil_preremplit_le_formulaire(): void
    {
        $response = $this->get('/calculateur?profil=cadre')->assertOk();

        $response->assertSee('Profil « Cadre » chargé', false);
        // Le profil cadre porte un salaire de 15 000 MAD et une ancienneté située
        // dans la deuxième tranche configurée (5 ans et plus).
        $response->assertSee('value="15000"', false);
        $this->assertStringContainsString('value="5" selected', $response->getContent());
    }

    public function test_un_profil_inconnu_laisse_le_formulaire_vide(): void
    {
        $response = $this->get('/calculateur?profil=profil-inexistant')->assertOk();

        $response->assertDontSee('chargé', false);
        $response->assertDontSee('value="15000"', false);
    }

    public function test_les_erreurs_de_validation_restent_prioritaires_sur_un_profil(): void
    {
        // Une soumission invalide renvoie au formulaire avec les saisies de
        // l'utilisateur : un profil dans l'URL ne doit pas les écraser.
        $response = $this->followingRedirects()
            ->from('/calculateur?profil=cadre')
            ->post('/calculateur/calculer', ['salaire_base' => '', 'type_frais_pro' => 'commun'])
            ->assertOk();

        $response->assertSee('Le salaire de base est obligatoire.');
        $response->assertDontSee('value="15000"', false);
    }

    // ------------------------------------------------------------------
    // Reprise et partage par URL (#50)
    // ------------------------------------------------------------------

    public function test_la_page_resultat_propose_un_lien_de_reprise(): void
    {
        $this->post('/calculateur/calculer', $this->scenario())
            ->assertOk()
            ->assertSee('Reprendre ou partager cette simulation')
            ->assertSee('Lien de la simulation')
            ->assertSee('Les montants saisis sont inscrits dans le lien lui-même', false);
    }

    public function test_le_lien_de_reprise_restaure_la_simulation(): void
    {
        $payload = $this->codec->encode($this->scenario([
            'salaire_base' => 13500,
            'nb_annees_anciennete' => 5,
            'cimr_taux' => 6,
        ]));

        $response = $this->get('/calculateur?s='.$payload)->assertOk();

        $response->assertSee('Simulation reprise depuis le lien partagé');
        $response->assertSee('value="13500"', false);
        $response->assertSee('value="6"', false);
        $this->assertStringContainsString('value="5" selected', $response->getContent());
    }

    public function test_un_lien_de_reprise_illisible_est_signale(): void
    {
        $this->get('/calculateur?s=payload-casse')
            ->assertOk()
            ->assertSee('Le lien de simulation est illisible ou expiré');
    }

    public function test_un_lien_de_reprise_illisible_ne_preremplit_rien(): void
    {
        $this->get('/calculateur?s=payload-casse')
            ->assertOk()
            ->assertDontSee('value="13500"', false);
    }

    // ------------------------------------------------------------------
    // Comparaison de scénarios (#47)
    // ------------------------------------------------------------------

    public function test_le_resultat_propose_de_comparer_avec_un_autre_scenario(): void
    {
        $this->post('/calculateur/calculer', $this->scenario())
            ->assertOk()
            ->assertSee('Comparer avec un autre scénario');
    }

    public function test_un_scenario_memorise_est_annonce_et_transporte_par_le_formulaire(): void
    {
        $payload = $this->codec->encode($this->scenario());

        $response = $this->get('/calculateur?s='.$payload.'&a='.$payload)->assertOk();

        $response->assertSee('Scénario A mémorisé');
        $response->assertSee('name="comparer_a"', false);
    }

    public function test_un_scenario_memorise_illisible_n_arme_pas_la_comparaison(): void
    {
        $this->get('/calculateur?a=payload-casse')
            ->assertOk()
            ->assertDontSee('name="comparer_a"', false);
    }

    public function test_calculer_avec_un_scenario_memorise_affiche_la_comparaison(): void
    {
        $payload = $this->codec->encode($this->scenario(['salaire_base' => 10000]));

        $response = $this->post('/calculateur/calculer', $this->scenario([
            'salaire_base' => 12000,
            'comparer_a' => $payload,
        ]))->assertOk();

        $response->assertSee('Scénario A contre scénario B');
        $response->assertSee('Ce qui change entre les deux scénarios');
        $response->assertSee('Comparaison détaillée');
        $response->assertSee('Salaire de base');
        $response->assertSee('modifié');
    }

    public function test_la_comparaison_est_rejouable_depuis_une_url(): void
    {
        $a = $this->codec->encode($this->scenario(['salaire_base' => 10000]));
        $b = $this->codec->encode($this->scenario(['salaire_base' => 12000]));

        $response = $this->get('/calculateur/comparer?a='.$a.'&b='.$b)->assertOk();

        $response->assertSee('Scénario A contre scénario B');
        $response->assertSee('Net à payer');
        $response->assertSee('Coût total employeur');
        $response->assertSee('Partager cette comparaison');
    }

    public function test_la_comparaison_affiche_les_ecarts_calcules(): void
    {
        $a = $this->codec->encode($this->scenario(['salaire_base' => 10000]));
        $b = $this->codec->encode($this->scenario(['salaire_base' => 12000]));

        $calculator = app(PayrollCalculatorService::class);
        $ecartNet = round(
            $calculator->calculer(['salaire_base' => 12000, 'type_frais_pro' => 'commun'])['salaire_net']
            - $calculator->calculer(['salaire_base' => 10000, 'type_frais_pro' => 'commun'])['salaire_net'],
            2,
        );

        $this->get('/calculateur/comparer?a='.$a.'&b='.$b)
            ->assertOk()
            ->assertSee('+ '.number_format($ecartNet, 2, ',', ' '))
            // Le brut passe de 10 000 à 12 000 MAD, soit +20%.
            ->assertSee('+ 20,00 %');
    }

    public function test_deux_scenarios_identiques_sont_signales_comme_tels(): void
    {
        $payload = $this->codec->encode($this->scenario());

        $this->get('/calculateur/comparer?a='.$payload.'&b='.$payload)
            ->assertOk()
            ->assertSee('Les entrées comparées sont identiques');
    }

    public function test_une_comparaison_illisible_renvoie_au_calculateur(): void
    {
        $payload = $this->codec->encode($this->scenario());

        $this->get('/calculateur/comparer?a='.$payload.'&b=casse')
            ->assertRedirect('/calculateur');

        $this->followingRedirects()
            ->get('/calculateur/comparer?a=casse&b=casse')
            ->assertOk()
            ->assertSee('La comparaison demandée est illisible');
    }

    public function test_la_comparaison_fonctionne_en_mode_net_vers_brut(): void
    {
        $a = $this->codec->encode(['mode' => 'net_to_gross', 'net_cible' => 8000, 'type_frais_pro' => 'commun']);
        $b = $this->codec->encode(['mode' => 'net_to_gross', 'net_cible' => 9000, 'type_frais_pro' => 'commun']);

        $this->get('/calculateur/comparer?a='.$a.'&b='.$b)
            ->assertOk()
            ->assertSee('Scénario A contre scénario B')
            ->assertSee('Net à payer cible');
    }

    public function test_la_page_de_comparaison_se_rend_dans_les_quatre_langues(): void
    {
        $a = $this->codec->encode($this->scenario(['salaire_base' => 10000]));
        $b = $this->codec->encode($this->scenario(['salaire_base' => 12000]));

        $attendus = [
            'fr' => 'Scénario A contre scénario B',
            'en' => 'Scenario A against scenario B',
            'es' => 'Escenario A frente al escenario B',
            'ar' => 'السيناريو A مقابل السيناريو B',
        ];

        foreach ($attendus as $locale => $titre) {
            $this->withSession(['locale' => $locale])
                ->get('/calculateur/comparer?a='.$a.'&b='.$b)
                ->assertOk()
                ->assertSee($titre, false);
        }
    }

    /**
     * Un libellé manquant se rend sous la forme de sa propre clé (« ui.xxx.yyy »).
     * Ce test attrape les clés référencées par les nouvelles vues mais absentes
     * des catalogues, dans les quatre langues.
     */
    public function test_les_nouveaux_ecrans_n_affichent_aucune_cle_de_traduction_brute(): void
    {
        $a = $this->codec->encode($this->scenario(['salaire_base' => 10000]));
        $b = $this->codec->encode($this->scenario(['salaire_base' => 12000, 'cimr_taux' => 6, 'nb_enfants' => 2]));

        $urls = [
            '/calculateur/comparer?a='.$a.'&b='.$b,
            '/calculateur?profil=cadre',
            '/calculateur?s='.$b.'&a='.$a,
            '/calculateur?s=payload-casse',
        ];

        foreach (array_keys(config('app.supported_locales')) as $locale) {
            foreach ($urls as $url) {
                $html = $this->withSession(['locale' => $locale])->get($url)->assertOk()->getContent();

                preg_match_all('/\bui\.[a-z_]+\.[a-z_.]+/', $html, $matches);

                $this->assertSame([], array_values(array_unique($matches[0])), "Clés non traduites sur {$url} en {$locale}");
            }
        }
    }
}
