<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use App\Services\SimulationProfileService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Confidentialité des montants saisis : aucune publicité sur le calculateur
 * (issues #283 et #300).
 *
 * Les pages calculator.* portent des montants de salaire : dans le formulaire
 * (saisie en cours, y compris sans payload), dans l'URL (?s=, ?a=, ?b=, payload
 * compressé mais non chiffré) ou dans la page résultat. AdSense est retiré du
 * site (#300) ; ce fichier garde la couverture de chaque parcours du
 * calculateur. Contre-exemple : en production, avec l'ancienne configuration
 * publicitaire forcée (ads.enabled, ads.client et les deux emplacements), aucune
 * page calculator.* ne contient de script, d'emplacement, d'attribut
 * data-ad-*, d'initialisation, de meta google-adsense-account ni l'identifiant
 * client.
 *
 * Les pages éditoriales sont couvertes par RetraitAdsenseTest.
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

        // Hors environnement testing, la protection CSRF s'applique aux POST
        // simulés. Laravel 13 l'assure par PreventRequestForgery (groupe web) ;
        // ValidateCsrfToken reste exclu pour les versions antérieures. Seuls
        // ces tests sont concernés : la production garde sa protection.
        $this->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
        $this->codec = app(SimulationCodec::class);

        // Production avec l'ancienne configuration publicitaire : seule configuration forcée.
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

        // Retrait complet (#300) : plus de meta de vérification ni d'identifiant client.
        $this->assertSame(0, $xpath->query('//meta[@name="google-adsense-account"]')->length, "{$ou} : aucune meta de vérification");
        $this->assertStringNotContainsString(self::CLIENT, $html, "{$ou} : identifiant client absent");
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
    // Parcours du calculateur
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
    // Page de gain 2027
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
}
