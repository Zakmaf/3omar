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
 * Confidentialité des montants saisis : aucune publicité sur le calculateur
 * (issue #283, revue indépendante 2).
 *
 * Les pages calculator.* portent des montants de salaire : dans le formulaire
 * (saisie en cours, y compris sans payload), dans l'URL (?s=, ?a=, ?b=, payload
 * compressé mais non chiffré) ou dans la page résultat. La régie publicitaire
 * reçoit l'URL et le contenu de la page qui charge son script. Contrat, en
 * production avec la publicité activée (ads.enabled, ads.client et les deux
 * emplacements configurés) :
 * - aucune page calculator.* ne contient le script réseau adsbygoogle, aucun
 *   élément ins.adsbygoogle, aucun attribut data-ad-client / data-ad-slot ni
 *   l'initialisation (window.adsbygoogle || []).push ;
 * - la balise inerte meta google-adsense-account (vérification du site, #176)
 *   peut rester : c'est la seule occurrence admise de l'identifiant client ;
 * - hors calculateur (accueil, documentation, fiabilité, API, guides), la
 *   publicité reste complète en production : script, deux emplacements,
 *   initialisation. Le correctif ne désactive pas la publicité globalement.
 *
 * Les tests n'installent aucune section, variable de vue ni configuration qui
 * réaliserait le correctif : seule la configuration publicitaire de
 * production est forcée.
 *
 * Statut attendu :
 * - parcours calculator.* existants (formulaire, restauration, comparaison,
 *   résultat) : ROUGE dès maintenant, la publicité y est rendue ;
 * - page calculator.gain2027 : test séparé, ROUGE sur l'arbre indexé actuel ;
 *   sur un HEAD sans la route, le rouge est masqué (404) ;
 * - publicité hors calculateur : VERT, garde-fou de non-régression.
 */
class CalculateurSansPubliciteTest extends TestCase
{
    private const CLIENT = 'ca-pub-2830000000000283';

    private const SLOT_HEADER = 'slot-header-283';

    private const SLOT_FOOTER = 'slot-footer-283';

    private const SAISIE_A = ['mode' => 'gross_to_net', 'salaire_base' => 12345, 'type_frais_pro' => 'commun'];

    private const SAISIE_B = ['mode' => 'gross_to_net', 'salaire_base' => 13579, 'type_frais_pro' => 'commun', 'exercice' => 2027];

    /** Routes nommées calculator.* couvertes par ce fichier. */
    private const ROUTES_COUVERTES = [
        'calculator.index',
        'calculator.calculer',
        'calculator.calculer.unavailable',
        'calculator.comparer',
        'calculator.gain2027',
    ];

    private SimulationCodec $codec;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->codec = app(SimulationCodec::class);

        // Production avec publicité activée : seule configuration forcée.
        $this->app['env'] = 'production';
        config()->set('ads.enabled', true);
        config()->set('ads.client', self::CLIENT);
        config()->set('ads.placements.header.slot', self::SLOT_HEADER);
        config()->set('ads.placements.footer.slot', self::SLOT_FOOTER);
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    private function dom(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $precedent = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($precedent);

        return new \DOMXPath($document);
    }

    private function assertSansPublicite(string $html, string $ou): void
    {
        $this->assertTrue(app()->environment('production'), 'Prémisse : environnement de production.');

        foreach (['adsbygoogle', 'googlesyndication', 'doubleclick', 'data-ad-client', 'data-ad-slot', self::SLOT_HEADER, self::SLOT_FOOTER] as $motif) {
            $this->assertStringNotContainsString($motif, $html, "{$ou} : aucune publicité ({$motif})");
        }

        $xpath = $this->dom($html);
        $this->assertSame(0, $xpath->query('//script[@src and not(starts-with(@src, "/")) and contains(@src, "google")]')->length, "{$ou} : aucun script réseau publicitaire");
        $this->assertSame(0, $xpath->query('//ins')->length, "{$ou} : aucun emplacement ins");
        $this->assertSame(0, $xpath->query('//aside[contains(concat(" ", normalize-space(@class), " "), " ad-slot ")]')->length, "{$ou} : aucun emplacement ad-slot");

        // Seule occurrence admise de l'identifiant client : la meta inerte de vérification.
        $metas = $xpath->query('//meta[@name="google-adsense-account"][@content="'.self::CLIENT.'"]')->length;
        $this->assertLessThanOrEqual(1, $metas, "{$ou} : au plus une meta de vérification");
        $this->assertSame($metas, substr_count($html, self::CLIENT), "{$ou} : identifiant client uniquement dans la meta de vérification");
    }

    private function assertAvecPublicite(string $html, string $ou): void
    {
        $xpath = $this->dom($html);

        $this->assertSame(
            1,
            $xpath->query('//script[@src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.self::CLIENT.'"]')->length,
            "{$ou} : script publicitaire chargé en production",
        );
        foreach ([self::SLOT_HEADER, self::SLOT_FOOTER] as $slot) {
            $this->assertSame(
                1,
                $xpath->query('//ins[contains(@class, "adsbygoogle")][@data-ad-client="'.self::CLIENT.'"][@data-ad-slot="'.$slot.'"]')->length,
                "{$ou} : emplacement {$slot} rendu",
            );
        }
        $this->assertStringContainsString('(window.adsbygoogle = window.adsbygoogle || []).push({});', $html, "{$ou} : emplacements initialisés");
    }

    private function payload(array $saisie): string
    {
        $payload = $this->codec->encode($saisie);
        $this->assertNotNull($this->codec->decode($payload), 'Prémisse : payload valide.');

        return $payload;
    }

    /** Requête réelle d'un parcours du calculateur, page finale rendue. */
    private function parcours(string $cas): TestResponse
    {
        $a = $this->payload(self::SAISIE_A);
        $b = $this->payload(self::SAISIE_B);

        return match ($cas) {
            'formulaire_vide' => $this->get('/calculateur'),
            'formulaire_profil' => $this->get('/calculateur?profil=standard'),
            'formulaire_restaure_s' => $this->get('/calculateur?s='.$a),
            'formulaire_comparaison_a' => $this->get('/calculateur?s='.$a.'&a='.$a),
            'formulaire_payload_invalide' => $this->get('/calculateur?s=pas-un-payload!'),
            'formulaire_apres_erreur_de_validation' => $this->followRedirects(
                $this->from('/calculateur')->post('/calculateur/calculer', ['type_frais_pro' => 'commun', 'prime_rendement' => 4321]),
            ),
            'acces_direct_resultat' => $this->followRedirects($this->get('/calculateur/calculer')),
            'resultat_brut' => $this->post('/calculateur/calculer', self::SAISIE_A),
            'resultat_2027' => $this->post('/calculateur/calculer', self::SAISIE_B),
            'resultat_net_cible' => $this->post('/calculateur/calculer', ['mode' => 'net_to_gross', 'net_cible' => 9876, 'type_frais_pro' => 'commun']),
            'comparaison_post' => $this->post('/calculateur/calculer', self::SAISIE_B + ['comparer_a' => $a]),
            'comparaison_get' => $this->get('/calculateur/comparer?a='.$a.'&b='.$b),
            'comparaison_invalide' => $this->followRedirects($this->get('/calculateur/comparer?a=x&b=y')),
        };
    }

    // ------------------------------------------------------------------
    // Parcours existants du calculateur (ROUGE dès maintenant)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function parcoursExistants(): array
    {
        $cas = [];
        foreach ([
            'formulaire_vide', 'formulaire_profil', 'formulaire_restaure_s', 'formulaire_comparaison_a',
            'formulaire_payload_invalide', 'formulaire_apres_erreur_de_validation', 'acces_direct_resultat',
            'resultat_brut', 'resultat_2027', 'resultat_net_cible', 'comparaison_post', 'comparaison_get',
            'comparaison_invalide',
        ] as $nom) {
            $cas[$nom] = [$nom];
        }

        return $cas;
    }

    #[DataProvider('parcoursExistants')]
    public function test_aucune_publicite_sur_les_parcours_du_calculateur(string $cas): void
    {
        if ($cas === 'formulaire_profil') {
            $this->assertTrue(app(SimulationProfileService::class)->exists('standard'), 'Prémisse : profil standard.');
        }

        $response = $this->parcours($cas)->assertOk();
        $this->assertTrue(request()->routeIs('calculator.*'), "Prémisse : {$cas} rendu par une route calculator.*.");

        $this->assertSansPublicite($response->getContent(), $cas);
    }

    public function test_aucune_publicite_en_arabe_sur_le_resultat(): void
    {
        $html = $this->withSession(['locale' => 'ar'])->post('/calculateur/calculer', self::SAISIE_A)->assertOk()->getContent();

        $this->assertSansPublicite($html, 'résultat ar');
    }

    // ------------------------------------------------------------------
    // Nouvelle page de gain 2027 (à prouver après GREEN)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{array}>
     */
    public static function saisiesGain2027(): array
    {
        return [
            'brut 2026' => [['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun']],
            'brut 2027' => [['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun', 'exercice' => 2027]],
            'net cible 2026' => [['mode' => 'net_to_gross', 'net_cible' => 12000, 'type_frais_pro' => 'commun']],
        ];
    }

    #[DataProvider('saisiesGain2027')]
    public function test_aucune_publicite_sur_la_page_de_gain_2027(array $saisie): void
    {
        $html = $this->get('/calculateur/gain-2027?s='.$this->payload($saisie))->assertOk()->getContent();
        $this->assertTrue(request()->routeIs('calculator.gain2027'), 'Prémisse : page calculator.gain2027.');

        $this->assertSansPublicite($html, 'gain 2027');
    }

    // ------------------------------------------------------------------
    // Couverture : toute route calculator.* est listée ici
    // ------------------------------------------------------------------

    public function test_toutes_les_routes_du_calculateur_sont_couvertes(): void
    {
        $noms = [];
        foreach (Route::getRoutes() as $route) {
            $nom = $route->getName();
            if (is_string($nom) && str_starts_with($nom, 'calculator.')) {
                $noms[] = $nom;
            }
        }

        $this->assertNotEmpty($noms);
        foreach ($noms as $nom) {
            $this->assertContains($nom, self::ROUTES_COUVERTES, "Route {$nom} : ajouter son parcours à ce test de confidentialité.");
        }
    }

    // ------------------------------------------------------------------
    // Hors calculateur : la publicité reste fonctionnelle (VERT)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function pagesHorsCalculateur(): array
    {
        return [
            'accueil' => ['/'],
            'documentation' => ['/documentation'],
            'documentation api' => ['/api-documentation'],
            'fiabilite' => ['/fiabilite'],
            'guide salaire net' => ['/calcul-salaire-net-maroc'],
        ];
    }

    #[DataProvider('pagesHorsCalculateur')]
    public function test_la_publicite_reste_active_hors_calculateur(string $uri): void
    {
        $html = $this->get($uri)->assertOk()->getContent();
        $this->assertFalse(request()->routeIs('calculator.*'), "Prémisse : {$uri} hors calculateur.");

        $this->assertAvecPublicite($html, $uri);
    }
}
