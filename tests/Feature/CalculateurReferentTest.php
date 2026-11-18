<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use App\Services\SimulationProfileService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Référent des pages du calculateur (issue #283, contre-revue).
 *
 * Avec Referrer-Policy: strict-origin-when-cross-origin, une navigation sur la
 * même origine transmet l'URL complète. Depuis /calculateur?s=<payload>,
 * /calculateur/comparer?a=...&b=... ou /calculateur/gain-2027?s=<payload>, la
 * page éditoriale suivante (qui charge AdSense en production) exposerait le
 * payload dans document.referrer. Contrat :
 * - toute réponse d'une route calculator.* (page, résultat, redirection)
 *   porte exactement un en-tête Referrer-Policy: no-referrer ;
 * - le changement de langue (/lang/...) aussi (#295) : le lien d'une page du
 *   calculateur porte l'URL de cette page dans sa cible de retour ;
 * - les autres réponses gardent strict-origin-when-cross-origin (pages
 *   éditoriales, API) ;
 * - sans référent, le retour après une erreur de validation suit la cible
 *   postée par le formulaire (champ caché, #295) et le retour d'un lien de
 *   langue historique sans cible passe encore par la session : ils
 *   reviennent à l'URL partagée ou au formulaire, sans perte de saisie. Les
 *   retours entre plusieurs onglets sont couverts par RetourMultiOngletsTest.
 *
 * Les requêtes de test n'envoient aucun en-tête Referer (ni from()) : c'est la
 * situation d'un navigateur sous no-referrer.
 *
 * Statut attendu sur le HEAD distant : ROUGE pour tous les cas calculator.*
 * existants (en-tête strict-origin-when-cross-origin). La page gain-2027 est
 * testée à part : son rouge est masqué tant que la route est absente (404).
 * Les garde-fous éditoriaux et de retour par la session sont VERTS dès
 * maintenant et doivent le rester.
 */
class CalculateurReferentTest extends TestCase
{
    private const SAISIE_A = ['mode' => 'gross_to_net', 'salaire_base' => 12345, 'type_frais_pro' => 'commun'];

    private const SAISIE_B = ['mode' => 'gross_to_net', 'salaire_base' => 13579, 'type_frais_pro' => 'commun', 'exercice' => 2027];

    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->codec = app(SimulationCodec::class);
    }

    private function payload(array $saisie): string
    {
        $payload = $this->codec->encode($saisie);
        $this->assertNotNull($this->codec->decode($payload), 'Prémisse : payload valide.');

        return $payload;
    }

    private function assertPolitique(TestResponse $response, string $attendue, string $ou): void
    {
        $valeurs = $response->headers->all('referrer-policy');
        $this->assertCount(1, $valeurs, "{$ou} : un seul en-tête Referrer-Policy");
        $this->assertSame($attendue, $valeurs[0], "{$ou} : Referrer-Policy {$attendue}");
    }

    /** Chemin et paramètres de requête d'une URL, indépendamment de leur ordre. */
    private function cheminEtRequete(string $url): array
    {
        $parties = parse_url($url);
        parse_str($parties['query'] ?? '', $query);
        ksort($query);

        return [$parties['path'] ?? '/', $query];
    }

    // ------------------------------------------------------------------
    // no-referrer sur les routes calculator.* existantes (ROUGE au HEAD)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function parcoursCalculateur(): array
    {
        return [
            'formulaire vide' => ['formulaire_vide', 200],
            'formulaire profil' => ['formulaire_profil', 200],
            'formulaire restauré s' => ['formulaire_restaure_s', 200],
            'formulaire comparaison s et a' => ['formulaire_comparaison_a', 200],
            'formulaire payload invalide' => ['formulaire_payload_invalide', 200],
            'résultat brut' => ['resultat_brut', 200],
            'résultat net cible' => ['resultat_net_cible', 200],
            'comparaison post' => ['comparaison_post', 200],
            'comparaison get' => ['comparaison_get', 200],
            'redirection comparaison invalide' => ['comparaison_invalide', 302],
            'redirection accès direct résultat' => ['acces_direct_resultat', 302],
            'redirection erreur de validation' => ['erreur_validation', 302],
        ];
    }

    private function parcours(string $cas): TestResponse
    {
        $a = $this->payload(self::SAISIE_A);
        $b = $this->payload(self::SAISIE_B);

        return match ($cas) {
            'formulaire_vide' => $this->get('/calculateur'),
            'formulaire_profil' => $this->get('/calculateur?profil=standard'),
            'formulaire_restaure_s' => $this->get('/calculateur?s='.$a),
            'formulaire_comparaison_a' => $this->get('/calculateur?s='.$a.'&a='.$a),
            'formulaire_payload_invalide' => $this->get('/calculateur?s=invalide'),
            'resultat_brut' => $this->post('/calculateur/calculer', self::SAISIE_A),
            'resultat_net_cible' => $this->post('/calculateur/calculer', ['mode' => 'net_to_gross', 'net_cible' => 9876, 'type_frais_pro' => 'commun']),
            'comparaison_post' => $this->post('/calculateur/calculer', self::SAISIE_B + ['comparer_a' => $a]),
            'comparaison_get' => $this->get('/calculateur/comparer?a='.$a.'&b='.$b),
            'comparaison_invalide' => $this->get('/calculateur/comparer?a=x&b=y'),
            'acces_direct_resultat' => $this->get('/calculateur/calculer'),
            'erreur_validation' => $this->post('/calculateur/calculer', ['type_frais_pro' => 'commun', 'prime_rendement' => 4321]),
        };
    }

    #[DataProvider('parcoursCalculateur')]
    public function test_les_routes_du_calculateur_ne_transmettent_aucun_referent(string $cas, int $statut): void
    {
        if ($cas === 'formulaire_profil') {
            $this->assertTrue(app(SimulationProfileService::class)->exists('standard'), 'Prémisse : profil standard.');
        }

        $response = $this->parcours($cas)->assertStatus($statut);
        $this->assertTrue(request()->routeIs('calculator.*'), "Prémisse : {$cas} servi par une route calculator.*.");

        $this->assertPolitique($response, 'no-referrer', $cas);
    }

    public function test_la_politique_ne_depend_pas_de_la_langue(): void
    {
        $response = $this->withSession(['locale' => 'ar'])->get('/calculateur?s='.$this->payload(self::SAISIE_A))->assertOk();

        $this->assertPolitique($response, 'no-referrer', 'formulaire restauré ar');
    }

    // ------------------------------------------------------------------
    // Page de gain 2027 (rouge masqué au HEAD sans la route)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{array|null, int}>
     */
    public static function requetesGain2027(): array
    {
        return [
            'page brut 2026' => [['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun'], 200],
            'page net cible 2026' => [['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun'], 200],
            'refus net cible 2027' => [['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun', 'exercice' => 2027], 302],
            'payload invalide' => [null, 302],
        ];
    }

    #[DataProvider('requetesGain2027')]
    public function test_la_page_de_gain_ne_transmet_aucun_referent(?array $saisie, int $statut): void
    {
        $s = $saisie === null ? 'invalide' : $this->payload($saisie);

        $response = $this->get('/calculateur/gain-2027?s='.$s)->assertStatus($statut);
        $this->assertTrue(request()->routeIs('calculator.gain2027'), 'Prémisse : route calculator.gain2027.');

        $this->assertPolitique($response, 'no-referrer', 'gain 2027');
    }

    // ------------------------------------------------------------------
    // Pages hors calculateur : politique conservée (VERT)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function pagesHorsCalculateur(): array
    {
        return [
            'accueil' => ['/', 200],
            'documentation' => ['/documentation', 200],
            'documentation api' => ['/api-documentation', 200],
            'fiabilite' => ['/fiabilite', 200],
            'guide salaire net' => ['/calcul-salaire-net-maroc', 200],
        ];
    }

    #[DataProvider('pagesHorsCalculateur')]
    public function test_les_pages_hors_calculateur_gardent_leur_politique(string $uri, int $statut): void
    {
        $response = $this->get($uri)->assertStatus($statut);
        $this->assertFalse(request()->routeIs('calculator.*', 'locale.update'), "Prémisse : {$uri} hors calculateur et hors langue.");

        $this->assertPolitique($response, 'strict-origin-when-cross-origin', $uri);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function changementsDeLangue(): array
    {
        return [
            'lien historique sans cible' => ['/lang/en', 302],
            'lien avec cible du calculateur' => ['/lang/ar?retour={cible}', 302],
            'cible refusée' => ['/lang/es?retour=%2F%2Fevil.example', 302],
            'langue inconnue' => ['/lang/xx?retour={cible}', 404],
        ];
    }

    /**
     * Le lien de langue d'une page du calculateur porte l'URL de cette page,
     * payload compris, dans sa cible (#295) : la réponse du changement de
     * langue ne transmet donc aucun référent, quelle que soit la cible.
     */
    #[DataProvider('changementsDeLangue')]
    public function test_le_changement_de_langue_ne_transmet_aucun_referent(string $uri, int $statut): void
    {
        $uri = str_replace('{cible}', rawurlencode('/calculateur?s='.$this->payload(self::SAISIE_A)), $uri);

        $response = $this->get($uri)->assertStatus($statut);
        $this->assertTrue(request()->routeIs('locale.update'), 'Prémisse : route locale.update.');

        $this->assertPolitique($response, 'no-referrer', $uri);
    }

    // ------------------------------------------------------------------
    // Sans référent : retours par la session, sans perte (VERT, garde-fous)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function urlsPartagees(): array
    {
        return [
            'formulaire restauré' => ['restaure'],
            'formulaire en comparaison' => ['comparaison_a'],
            'comparaison' => ['comparer'],
        ];
    }

    private function urlPartagee(string $cas): string
    {
        $a = $this->payload(self::SAISIE_A);
        $b = $this->payload(self::SAISIE_B);

        return match ($cas) {
            'restaure' => '/calculateur?s='.$a,
            'comparaison_a' => '/calculateur?s='.$a.'&a='.$a,
            'comparer' => '/calculateur/comparer?a='.$a.'&b='.$b,
        };
    }

    #[DataProvider('urlsPartagees')]
    public function test_le_changement_de_langue_sans_referent_revient_a_l_url_partagee(string $cas): void
    {
        $url = $this->urlPartagee($cas);
        $this->get($url)->assertOk();

        $response = $this->get('/lang/en')->assertRedirect();
        $this->assertNull(request()->headers->get('referer'), 'Prémisse : aucun référent envoyé.');
        $response->assertSessionHas('locale', 'en');

        $this->assertSame(
            $this->cheminEtRequete($url),
            $this->cheminEtRequete((string) $response->headers->get('Location')),
            'Retour à la même URL partagée, payload compris, jamais à l\'accueil.',
        );

        $this->get((string) $response->headers->get('Location'))->assertOk()->assertSee('lang="en"', false);
    }

    public function test_le_changement_de_langue_depuis_la_page_de_gain_revient_a_la_meme_url(): void
    {
        $url = '/calculateur/gain-2027?s='.$this->payload(['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun']);
        $this->get($url)->assertOk();

        $response = $this->get('/lang/ar')->assertRedirect();

        $this->assertSame($this->cheminEtRequete($url), $this->cheminEtRequete((string) $response->headers->get('Location')));
        $this->get((string) $response->headers->get('Location'))->assertOk()->assertSee('dir="rtl"', false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formulairesDeDepart(): array
    {
        return [
            'formulaire vide' => ['/calculateur'],
            'formulaire restauré' => ['restaure'],
        ];
    }

    #[DataProvider('formulairesDeDepart')]
    public function test_une_erreur_de_validation_sans_referent_revient_au_formulaire_sans_perte(string $depart): void
    {
        $url = $depart === 'restaure' ? $this->urlPartagee('restaure') : $depart;
        $formulaire = $this->get($url)->assertOk()->getContent();

        // Saisie invalide (salaire de base manquant) avec d'autres montants,
        // envoyés comme un formulaire HTML (chaînes), sans en-tête Referer,
        // avec la cible de retour rendue par le formulaire (#295).
        $response = $this->post('/calculateur/calculer', [
            'retour' => $this->valeurChamp($this->dom($formulaire), 'retour', 'hidden'),
            'mode' => 'gross_to_net',
            'type_frais_pro' => 'commun',
            'prime_rendement' => '4321',
            'retenues_imposees_ir' => '55',
        ]);
        $this->assertNull(request()->headers->get('referer'), 'Prémisse : aucun référent envoyé.');

        $response->assertRedirect();
        $this->assertSame(
            $this->cheminEtRequete(url($url)),
            $this->cheminEtRequete((string) $response->headers->get('Location')),
            'Retour au formulaire d\'origine, jamais à l\'accueil.',
        );
        $response->assertSessionHasErrors('salaire_base');
        $response->assertSessionHasInput('prime_rendement', '4321');
        $response->assertSessionHasInput('retenues_imposees_ir', '55');

        // La saisie de l'utilisateur prime sur le préremplissage du lien. Le
        // formulaire regroupe par conception prime_bilan, prime_rendement et
        // autres_primes dans le seul champ visible autres_primes (somme des
        // trois), les deux premiers restant des champs cachés à 0 : la prime
        // saisie est donc réaffichée, montant compris, dans autres_primes.
        $html = $this->get((string) $response->headers->get('Location'))->assertOk()->getContent();
        $xpath = $this->dom($html);
        $this->assertSame(4321.0, (float) $this->valeurChamp($xpath, 'autres_primes'), 'Prime conservée dans le champ des primes imposables.');
        $this->assertSame('0', $this->valeurChamp($xpath, 'prime_rendement', 'hidden'), 'Pas de double comptage de la prime.');
        $this->assertSame(55.0, (float) $this->valeurChamp($xpath, 'retenues_imposees_ir'), 'Retenue conservée.');
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

    /** Valeur de l'unique champ portant ce nom (et ce type, si précisé). */
    private function valeurChamp(\DOMXPath $xpath, string $nom, ?string $type = null): string
    {
        $filtre = $type === null ? '' : '[@type="'.$type.'"]';
        $champs = $xpath->query('//form[@id="payrollForm"]//input[@name="'.$nom.'"]'.$filtre);
        $this->assertSame(1, $champs->length, "Un seul champ {$nom}.");

        return $champs->item(0)->getAttribute('value');
    }

    // ------------------------------------------------------------------
    // Couverture : toute route calculator.* est connue de ce test
    // ------------------------------------------------------------------

    public function test_toutes_les_routes_du_calculateur_sont_couvertes(): void
    {
        $couvertes = ['calculator.index', 'calculator.calculer', 'calculator.calculer.unavailable', 'calculator.comparer', 'calculator.gain2027'];

        foreach (Route::getRoutes() as $route) {
            $nom = $route->getName();
            if (is_string($nom) && str_starts_with($nom, 'calculator.')) {
                $this->assertContains($nom, $couvertes, "Route {$nom} : ajouter son cas au test du référent.");
            }
        }
    }
}
