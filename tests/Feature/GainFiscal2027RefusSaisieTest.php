<?php

namespace Tests\Feature;

use App\Http\Requests\PayrollValidation;
use App\Services\ExercicesFiscaux;
use App\Services\PayrollCalculatorService;
use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Refus de la page « Mon gain fiscal en janvier 2027 » sans perte de saisie
 * (issue #283, revue indépendante 2), par le vrai chemin HTTP.
 *
 * Contrat :
 * - tout refus direct de GET /calculateur/gain-2027?s=<payload valide>
 *   (net cible 2027, catégorie non calculable en 2027, net cible non atteint
 *   par le solveur) redirige vers /calculateur?s=<même saisie>. Le paramètre s
 *   est l'unique paramètre de la redirection et se décode en la saisie reçue,
 *   sans modification. Le formulaire rouvert est prérempli avec cette saisie
 *   et affiche le motif du refus ;
 * - net cible non atteint : erreur traduite ui.gain2027.net_target_not_reached
 *   sous la clé calculator ;
 * - net cible 2027 : notice ui.gain2027.net_source_2027_refused (inchangée) ;
 * - catégorie non calculable : erreur ui.validation.category_not_supported
 *   sous la clé calculator (inchangée) ;
 * - la page résultat d'un net cible 2027 ne propose aucun lien vers la page de
 *   gain (refus certain). Elle affiche à la place une notice
 *   [data-gain2027-refus] portant le texte ui.gain2027.net_source_2027_refused
 *   et un lien vers /calculateur?s=<payload du résultat> pour repartir de la
 *   même saisie. Même règle pour un net cible 2026 non atteint par le
 *   solveur (notice portant ui.gain2027.net_target_not_reached).
 *
 * Ce fichier ne modifie pas les attentes de GainFiscal2027WebTest : son test
 * test_un_net_cible_2027_est_refuse_explicitement (redirection sans s) sera
 * aligné au GREEN.
 *
 * Statut attendu sur l'arbre indexé actuel : ROUGE (redirections sans s, net
 * cible non atteint affiché en 200, lien d'appel présent pour un net cible
 * 2027). Sur un HEAD sans la route gain-2027, le rouge est masqué (404).
 */
class GainFiscal2027RefusSaisieTest extends TestCase
{
    private const CLE_NON_ATTEINT = 'ui.gain2027.net_target_not_reached';

    private const LOCALES = ['fr', 'en', 'ar', 'es'];

    private const NET_HORS_BORNE = [
        'mode' => 'net_to_gross',
        'net_cible' => PayrollValidation::MONTANT_MAX,
        'type_frais_pro' => 'commun',
        'retenues_imposees_ir' => PayrollValidation::MONTANT_MAX,
    ];

    private const NET_DEJA_ATTEINT_PRIMES = [
        'mode' => 'net_to_gross',
        'net_cible' => 1000,
        'type_frais_pro' => 'commun',
        'prime_rendement' => 5000,
    ];

    private const NET_DEJA_ATTEINT_INDEMNITE = [
        'mode' => 'net_to_gross',
        'net_cible' => 300,
        'type_frais_pro' => 'commun',
        'indemnites' => [['type' => 'transport', 'montant' => 500]],
    ];

    private const NET_2027 = ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun', 'exercice' => 2027];

    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->codec = app(SimulationCodec::class);
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** Saisie telle que le contrôleur la reçoit : encodée puis revalidée. */
    private function decodee(array $saisie): array
    {
        $decodee = $this->codec->decode($this->codec->encode($saisie));
        $this->assertNotNull($decodee, 'Prémisse : payload valide.');

        return $decodee;
    }

    private function ouvrirGain(array $saisie, string $locale = 'fr'): TestResponse
    {
        return $this->withSession(['locale' => $locale])
            ->get('/calculateur/gain-2027?s='.$this->codec->encode($saisie));
    }

    private function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $precedent = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        return new \DOMXPath($document);
    }

    private function texte(\DOMNode $noeud): string
    {
        return trim(preg_replace('/\s+/u', ' ', $noeud->textContent));
    }

    /** Vérifie la redirection vers /calculateur?s=<saisie inchangée>. */
    private function assertRetourAvecSaisie(TestResponse $response, array $saisieDecodee): void
    {
        $response->assertRedirect();
        $parties = parse_url((string) $response->headers->get('Location'));

        $this->assertSame(parse_url(route('calculator.index'), PHP_URL_PATH), $parties['path'] ?? '', 'Retour au calculateur.');
        parse_str($parties['query'] ?? '', $query);
        $this->assertSame(['s'], array_keys($query), 'La saisie est conservée par le seul paramètre s.');
        $this->assertSame($saisieDecodee, $this->codec->decode($query['s']), 'Saisie conservée sans modification.');
    }

    private function valeurChamp(\DOMXPath $xpath, string $id): string
    {
        $champ = $xpath->query('//input[@id="'.$id.'"]');
        $this->assertSame(1, $champ->length, "Champ {$id} présent.");

        return $champ->item(0)->getAttribute('value');
    }

    /** Formulaire rouvert : prérempli en net cible avec la cible saisie. */
    private function assertFormulaireNetPrerempli(string $html, array $saisieDecodee): \DOMXPath
    {
        $xpath = $this->dom($html);

        $this->assertTrue(
            $xpath->query('//input[@id="modeNetToGross"]')->item(0)->hasAttribute('checked'),
            'Mode net cible conservé.',
        );
        $this->assertSame((float) $saisieDecodee['net_cible'], (float) $this->valeurChamp($xpath, 'net_cible'), 'Net cible conservé.');

        return $xpath;
    }

    private function espionNonConvergent(): void
    {
        $this->app->instance(PayrollCalculatorService::class, new class extends PayrollCalculatorService
        {
            public function __construct()
            {
                parent::__construct(app(ExercicesFiscaux::class));
            }

            public function resoudreDepuisNet(array $input): array
            {
                $resultat = parent::resoudreDepuisNet($input);
                $resultat['resolution_net']['converge'] = false;

                return $resultat;
            }
        });
    }

    // ------------------------------------------------------------------
    // Net cible non atteint
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{array}>
     */
    public static function ciblesNonAtteintes(): array
    {
        return [
            'hors borne du solveur' => [self::NET_HORS_BORNE],
            'deja depassee par des primes' => [self::NET_DEJA_ATTEINT_PRIMES],
            'deja depassee par une indemnite exoneree' => [self::NET_DEJA_ATTEINT_INDEMNITE],
        ];
    }

    #[DataProvider('ciblesNonAtteintes')]
    public function test_un_net_cible_non_atteint_revient_au_formulaire_avec_la_saisie(array $saisie): void
    {
        $decodee = $this->decodee($saisie);

        // Prémisse sur le vrai moteur, comme le service la demande (2026).
        $resolu = app(PayrollCalculatorService::class)->resoudreDepuisNet(array_merge($decodee, ['exercice' => 2026]));
        $this->assertFalse($resolu['resolution_net']['converge'], 'Prémisse : cible non atteinte par le vrai moteur.');

        $message = trans(self::CLE_NON_ATTEINT, [], 'fr');
        $this->assertNotSame(self::CLE_NON_ATTEINT, $message, 'Message traduit attendu.');

        $response = $this->ouvrirGain($saisie);
        $this->assertRetourAvecSaisie($response, $decodee);
        $response->assertSessionHasErrors(['calculator' => $message]);

        $html = $this->followRedirects($response)->assertOk()->getContent();
        $this->assertStringContainsString(e($message), $html, 'Motif du refus affiché sur le formulaire.');
        $this->assertFormulaireNetPrerempli($html, $decodee);
    }

    public function test_une_non_convergence_forcee_revient_au_formulaire_avec_la_saisie(): void
    {
        $saisie = ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun'];
        $decodee = $this->decodee($saisie);
        $this->espionNonConvergent();

        $response = $this->ouvrirGain($saisie);
        $this->assertRetourAvecSaisie($response, $decodee);
        $response->assertSessionHasErrors(['calculator' => trans(self::CLE_NON_ATTEINT, [], 'fr')]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        $cas = [];
        foreach (self::LOCALES as $locale) {
            $cas[$locale] = [$locale];
        }

        return $cas;
    }

    #[DataProvider('locales')]
    public function test_le_refus_net_non_atteint_est_traduit(string $locale): void
    {
        $message = trans(self::CLE_NON_ATTEINT, [], $locale);
        $this->assertNotSame(self::CLE_NON_ATTEINT, $message, "{$locale} : clé absente du catalogue");

        $response = $this->ouvrirGain(self::NET_HORS_BORNE, $locale);
        $this->assertRetourAvecSaisie($response, $this->decodee(self::NET_HORS_BORNE));
        $response->assertSessionHasErrors(['calculator' => $message]);

        $html = $this->followRedirects($response)->assertOk()->getContent();
        $this->assertStringContainsString(e($message), $html, "{$locale} : motif affiché");
        $this->assertStringNotContainsString('ui.gain2027.', $html, "{$locale} : aucune clé brute");
    }

    // ------------------------------------------------------------------
    // Autres refus directs : saisie conservée
    // ------------------------------------------------------------------

    public function test_un_net_cible_2027_revient_au_formulaire_avec_la_saisie(): void
    {
        $decodee = $this->decodee(self::NET_2027);

        $response = $this->ouvrirGain(self::NET_2027);
        $this->assertRetourAvecSaisie($response, $decodee);
        $response->assertSessionHas('calculator_notice', 'ui.gain2027.net_source_2027_refused');

        $html = $this->followRedirects($response)->assertOk()->getContent();
        $this->assertStringContainsString(e(trans('ui.gain2027.net_source_2027_refused', [], 'fr')), $html, 'Notice affichée.');

        $xpath = $this->assertFormulaireNetPrerempli($html, $decodee);
        $choisi = $xpath->query('//select[@id="exercice"]/option[@selected]');
        $this->assertSame(1, $choisi->length, 'Un exercice présélectionné.');
        $this->assertSame('2027', $choisi->item(0)->getAttribute('value'), 'Exercice 2027 conservé, jamais remplacé par 2026.');
    }

    public function test_une_categorie_non_calculable_revient_au_formulaire_avec_la_saisie(): void
    {
        config(['payroll.exercices.2027.frais_pro.categories' => ['commun', 'journaliste']]);
        $saisie = ['mode' => 'gross_to_net', 'salaire_base' => 10000, 'type_frais_pro' => 'artiste'];
        $decodee = $this->decodee($saisie);
        $message = __('ui.validation.category_not_supported', ['exercice' => 2027]);

        $response = $this->ouvrirGain($saisie);
        $this->assertRetourAvecSaisie($response, $decodee);
        $response->assertSessionHasErrors(['calculator' => $message]);

        $html = $this->followRedirects($response)->assertOk()->getContent();
        $this->assertStringContainsString(e($message), $html, 'Motif affiché.');

        $xpath = $this->dom($html);
        $this->assertSame(10000.0, (float) $this->valeurChamp($xpath, 'salaire_base'), 'Salaire de base conservé.');
        $categorie = $xpath->query('//select[@id="type_frais_pro"]/option[@selected]');
        $this->assertSame(1, $categorie->length);
        $this->assertSame('artiste', $categorie->item(0)->getAttribute('value'), 'Catégorie conservée.');
    }

    // ------------------------------------------------------------------
    // Page résultat d'un net cible 2027 : aucun lien vers un refus certain
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_le_resultat_d_un_net_cible_2027_ne_mene_pas_a_un_refus(string $locale): void
    {
        $response = $this->withSession(['locale' => $locale])
            ->post('/calculateur/calculer', self::NET_2027)
            ->assertOk();
        $xpath = $this->dom($response->getContent());

        $this->assertSame(0, $xpath->query('//a[@data-gain2027-cta]')->length, "{$locale} : aucun lien d'appel vers un refus certain.");
        foreach ($xpath->query('//a[@href]') as $lien) {
            $this->assertStringNotContainsString('/calculateur/gain-2027', $lien->getAttribute('href'), "{$locale} : aucun lien vers la page de gain.");
        }

        $notices = $xpath->query('//*[@data-gain2027-refus]');
        $this->assertSame(1, $notices->length, "{$locale} : notice explicite à la place du lien.");
        $notice = $notices->item(0);
        $this->assertStringContainsString(trans('ui.gain2027.net_source_2027_refused', [], $locale), $this->texte($notice));

        // Repartir avec ses entrées : lien vers le formulaire prérempli.
        $attendue = $this->codec->decode($response->viewData('share_payload'));
        $this->assertNotNull($attendue);
        $retours = [];
        foreach ($xpath->query('.//a[@href]', $notice) as $lien) {
            $parties = parse_url($lien->getAttribute('href'));
            parse_str($parties['query'] ?? '', $query);
            if (($parties['path'] ?? '') === '/calculateur' && is_string($query['s'] ?? null)) {
                $retours[] = $this->codec->decode($query['s']);
            }
        }
        $this->assertContains($attendue, $retours, "{$locale} : la notice permet de repartir de la même saisie.");
    }

    /**
     * Même principe pour un net cible 2026 que le solveur n'atteint pas : la
     * page de gain le refusera à coup sûr, le résultat ne doit donc pas y
     * mener. Extension directe du point 3 de la revue (aucun lien vers un refus
     * certain) ; notice [data-gain2027-refus] portant
     * ui.gain2027.net_target_not_reached et lien de retour avec la saisie.
     */
    public function test_le_resultat_d_un_net_cible_non_atteint_ne_mene_pas_a_un_refus(): void
    {
        $response = $this->post('/calculateur/calculer', self::NET_HORS_BORNE + ['exercice' => 2026])->assertOk();
        $this->assertFalse($response->viewData('r')['resolution_net']['converge'], 'Prémisse : cible non atteinte.');
        $xpath = $this->dom($response->getContent());

        $this->assertSame(0, $xpath->query('//a[@data-gain2027-cta]')->length, 'Aucun lien d\'appel vers un refus certain.');

        $notices = $xpath->query('//*[@data-gain2027-refus]');
        $this->assertSame(1, $notices->length, 'Notice explicite à la place du lien.');
        $this->assertStringContainsString(trans(self::CLE_NON_ATTEINT, [], 'fr'), $this->texte($notices->item(0)));

        $attendue = $this->codec->decode($response->viewData('share_payload'));
        $retours = [];
        foreach ($xpath->query('.//a[@href]', $notices->item(0)) as $lien) {
            $parties = parse_url($lien->getAttribute('href'));
            parse_str($parties['query'] ?? '', $query);
            if (($parties['path'] ?? '') === '/calculateur' && is_string($query['s'] ?? null)) {
                $retours[] = $this->codec->decode($query['s']);
            }
        }
        $this->assertContains($attendue, $retours, 'La notice permet de repartir de la même saisie.');
    }

    public function test_le_resultat_d_un_net_cible_2026_garde_le_lien_d_appel(): void
    {
        // Garde-fou : seul le net cible 2027 perd le lien.
        $xpath = $this->dom($this->post('/calculateur/calculer', ['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun', 'exercice' => 2026])->assertOk()->getContent());

        $this->assertSame(1, $xpath->query('//a[@data-gain2027-cta]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-gain2027-refus]')->length);
    }
}
