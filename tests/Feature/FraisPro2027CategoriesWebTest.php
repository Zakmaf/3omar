<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Catégories de frais professionnels de l'exercice 2027 (issue #282) aux
 * portes publiques web : formulaire (POST /calculateur/calculer), lien partagé
 * (codec, reprise /calculateur?s=) et comparaison (/calculateur/comparer).
 * L'API et le moteur sont couverts par FraisPro2027ApiTest et FraisPro2027Test.
 *
 * - Profil réel : les trois catégories sont rattachées au taux unique.
 * - Profil complet volontairement restreint (artiste retiré de `categories`) :
 *   artiste est refusé partout sur le seul champ type_frais_pro, les catégories
 *   rattachées restent acceptées, et 2026 n'est pas affecté. Aucun repli
 *   silencieux vers un autre régime.
 */
class FraisPro2027CategoriesWebTest extends TestCase
{
    private const SAISIE = ['mode' => 'gross_to_net', 'salaire_base' => 5000];

    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->codec = app(SimulationCodec::class);
    }

    /**
     * Profil réel complet, puis retrait de la seule catégorie artiste. La
     * surcharge ne fabrique pas de profil partiel : le reste est vérifié avant.
     */
    private function restreindreSansArtiste(): void
    {
        $fraisPro = config('payroll.exercices.2027.frais_pro');

        $this->assertIsArray($fraisPro, 'Prémisse : profil 2027 paramétré.');
        foreach (['taux', 'plafond_annuel', 'reference'] as $cle) {
            $this->assertArrayHasKey($cle, $fraisPro, "Prémisse : clé {$cle} du profil 2027.");
        }
        $this->assertSame(['commun', 'journaliste', 'artiste'], $fraisPro['categories'] ?? null, 'Prémisse : profil réel avant restriction.');

        config(['payroll.exercices.2027.frais_pro.categories' => ['commun', 'journaliste']]);
    }

    private function messageRefus(): string
    {
        $message = __('ui.validation.category_not_supported', ['exercice' => 2027]);
        $this->assertNotSame('ui.validation.category_not_supported', $message, 'Message traduit attendu.');

        return $message;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function categories(): array
    {
        return ['commun' => ['commun'], 'journaliste' => ['journaliste'], 'artiste' => ['artiste']];
    }

    // ------------------------------------------------------------------
    // Profil réel
    // ------------------------------------------------------------------

    #[DataProvider('categories')]
    public function test_profil_reel_le_formulaire_calcule_chaque_categorie_au_taux_unique(string $categorie): void
    {
        $response = $this->post('/calculateur/calculer', self::SAISIE + ['type_frais_pro' => $categorie, 'exercice' => '2027'])
            ->assertOk()
            ->assertSessionHasNoErrors();

        $this->assertSame('commun_unique', $response->viewData('r')['regime_fp'], $categorie);
        $this->assertSame(2027, $response->viewData('r')['exercice']);
    }

    #[DataProvider('categories')]
    public function test_profil_reel_le_lien_partage_transporte_chaque_categorie(string $categorie): void
    {
        $decode = $this->codec->decode($this->codec->encode(self::SAISIE + ['type_frais_pro' => $categorie, 'exercice' => 2027]));

        $this->assertNotNull($decode, $categorie);
        $this->assertSame($categorie, $decode['type_frais_pro']);
        $this->assertEquals(2027, $decode['exercice']);
    }

    // ------------------------------------------------------------------
    // Profil complet restreint
    // ------------------------------------------------------------------

    public function test_profil_restreint_le_formulaire_refuse_la_categorie_non_rattachee(): void
    {
        $this->restreindreSansArtiste();
        $message = $this->messageRefus();

        $this->from('/calculateur')
            ->post('/calculateur/calculer', self::SAISIE + ['type_frais_pro' => 'artiste', 'exercice' => '2027'])
            ->assertRedirect('/calculateur')
            ->assertSessionHasErrors(['type_frais_pro' => $message])
            ->assertSessionDoesntHaveErrors(['exercice', 'calculator']);

        $erreurs = session('errors')->getBag('default')->keys();
        $this->assertSame(['type_frais_pro'], $erreurs, 'Seul type_frais_pro est en erreur.');
    }

    public function test_profil_restreint_le_formulaire_accepte_les_categories_rattachees_et_2026(): void
    {
        $this->restreindreSansArtiste();

        foreach (['commun', 'journaliste'] as $categorie) {
            $r = $this->post('/calculateur/calculer', self::SAISIE + ['type_frais_pro' => $categorie, 'exercice' => '2027'])
                ->assertOk()
                ->assertSessionHasNoErrors()
                ->viewData('r');

            $this->assertSame('commun_unique', $r['regime_fp'], $categorie);
        }

        $r2026 = $this->post('/calculateur/calculer', self::SAISIE + ['type_frais_pro' => 'artiste', 'exercice' => '2026'])
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->viewData('r');

        $this->assertSame('artiste', $r2026['regime_fp'], '2026 garde sa catégorie artiste.');
        $this->assertSame(2026, $r2026['exercice']);
    }

    public function test_profil_restreint_le_lien_partage_refuse_la_categorie_non_rattachee(): void
    {
        $this->restreindreSansArtiste();

        $artiste2027 = $this->codec->encode(self::SAISIE + ['type_frais_pro' => 'artiste', 'exercice' => 2027]);
        $journaliste2027 = $this->codec->encode(self::SAISIE + ['type_frais_pro' => 'journaliste', 'exercice' => 2027]);
        $artiste2026 = $this->codec->encode(self::SAISIE + ['type_frais_pro' => 'artiste', 'exercice' => 2026]);

        $this->assertNull($this->codec->decode($artiste2027), 'Catégorie non rattachée : lien refusé, jamais recalculé autrement.');
        $this->assertNotNull($this->codec->decode($journaliste2027), 'Catégorie rattachée : lien accepté.');
        $this->assertNotNull($this->codec->decode($artiste2026), '2026 : artiste reste transportable.');

        // Reprise publique : le lien refusé est signalé, rien n'est restauré ;
        // le lien d'une catégorie rattachée est restauré.
        $refuse = $this->get('/calculateur?s='.$artiste2027)->assertOk();
        $this->assertTrue($refuse->viewData('share_payload_invalid'), 'Lien refusé signalé.');
        $this->assertFalse($refuse->viewData('simulation_restored'), 'Aucune saisie restaurée depuis un lien refusé.');

        $accepte = $this->get('/calculateur?s='.$journaliste2027)->assertOk();
        $this->assertFalse($accepte->viewData('share_payload_invalid'));
        $this->assertTrue($accepte->viewData('simulation_restored'), 'Catégorie rattachée : saisie restaurée.');

        // Comparaison publique : un scénario refusé invalide la comparaison.
        $this->get('/calculateur/comparer?a='.$artiste2026.'&b='.$artiste2027)
            ->assertRedirect(route('calculator.index'))
            ->assertSessionHas('calculator_notice', 'ui.calculator.comparison_invalid_notice');

        $this->get('/calculateur/comparer?a='.$artiste2026.'&b='.$journaliste2027)->assertOk();
    }
}
