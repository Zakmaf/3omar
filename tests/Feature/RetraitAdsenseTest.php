<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Retrait complet d'AdSense et confidentialité sincère (issue #300, #165
 * questions 5 et 6).
 *
 * Contrat :
 * 1. En production, avec l'ancienne configuration publicitaire activée et
 *    renseignée (valeurs synthétiques), aucune page, éditoriale ou du
 *    calculateur, ne sert de balise Google AdSense : ni meta de vérification,
 *    ni script réseau, ni emplacement, ni initialisation. Les pages restent
 *    rendues (HTTP 200, route et document attendus), contrôlé séparément.
 * 2. La CSP ne déclare aucun hôte publicitaire, même dans ces conditions, et
 *    garde les CDN non publicitaires requis. /ads.txt n'est plus une route.
 * 3. /fiabilite affiche dans les quatre locales le paragraphe de
 *    confidentialité de référence, sans intitulé de confidentialité absolue,
 *    et garde le sens RTL en arabe.
 *
 * Aucun nouveau nom de classe, de clé ou de constante n'est supposé : seuls
 * le rendu HTTP, les en-têtes et la table des routes sont observés.
 *
 * Statut attendu avant correctif :
 * - absence de script, emplacements et initialisation : ROUGE sur les pages
 *   éditoriales, VERT (garde-fou) sur le calculateur ;
 * - absence de meta de vérification et d'identifiant client : ROUGE partout ;
 * - statut et identité des pages : VERT (garde-fou) ;
 * - CSP sans hôte publicitaire : ROUGE ; CDN requis conservés : VERT ;
 * - /ads.txt en 404 : ROUGE ;
 * - paragraphe de référence et absence d'intitulé absolu : ROUGE ;
 * - RTL arabe : VERT (garde-fou).
 */
class RetraitAdsenseTest extends TestCase
{
    // Valeurs synthétiques, jamais un identifiant réel.
    private const PUBLISHER = 'pub-3000000000000300';

    private const CLIENT = 'ca-pub-3000000000000300';

    private const SLOT_HEADER = 'slot-header-300';

    private const SLOT_FOOTER = 'slot-footer-300';

    /** Saisie synthétique du calculateur. */
    private const SAISIE = ['mode' => 'gross_to_net', 'salaire_base' => 11111, 'type_frais_pro' => 'commun'];

    /** Fragments d'hôtes ou de marqueurs publicitaires Google. */
    private const MARQUEURS_PUBLICITAIRES = [
        'adsbygoogle',
        'googlesyndication',
        'doubleclick',
        'googletagservices',
        'googleads',
        'adservice.google',
    ];

    private const TEXTES_CONFIDENTIALITE = [
        'fr' => 'Vos simulations sont calculées à la demande et ne sont pas enregistrées dans une base de données. Les liens de partage contiennent les valeurs saisies. Ces URL peuvent être visibles par les services techniques qui acheminent les requêtes. Ne partagez pas un lien contenant des informations confidentielles.',
        'en' => 'Your simulations are calculated on request and are not saved in a database. Shared links contain the values you entered. These URLs may be visible to technical services handling requests. Do not share links containing confidential information.',
        'ar' => 'تُحسب عمليات المحاكاة عند الطلب ولا تُحفظ في قاعدة بيانات. تحتوي روابط المشاركة على القيم التي أدخلتها، وقد تكون عناوين هذه الروابط مرئية للخدمات التقنية التي تعالج الطلبات. لا تشارك روابط تتضمن معلومات سرية.',
        'es' => 'Tus simulaciones se calculan bajo demanda y no se guardan en una base de datos. Los enlaces compartidos contienen los valores introducidos. Estas URL pueden ser visibles para los servicios técnicos que procesan las solicitudes. No compartas enlaces que contengan información confidencial.',
    ];

    /** Anciens intitulés de confidentialité absolue, et leur racine par locale. */
    private const INTITULES_ABSOLUS = [
        'fr' => ['Confidentialité absolue', 'absolu'],
        'en' => ['Absolute privacy', 'absolute'],
        'ar' => ['خصوصية مطلقة', 'مطلق'],
        'es' => ['Privacidad absoluta', 'absolut'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Hors environnement testing, la protection CSRF s'applique aux POST
        // simulés (résultat du calculateur). Seuls ces tests sont concernés.
        $this->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]);
    }

    // ------------------------------------------------------------------
    // Outils
    // ------------------------------------------------------------------

    /** Production avec l'ancienne configuration publicitaire active et renseignée. */
    private function activerAncienneConfigPublicitaire(): void
    {
        $this->app['env'] = 'production';
        config()->set('ads.enabled', true);
        config()->set('ads.publisher_id', self::PUBLISHER);
        config()->set('ads.client', self::CLIENT);
        config()->set('ads.placements.header.slot', self::SLOT_HEADER);
        config()->set('ads.placements.footer.slot', self::SLOT_FOOTER);

        $this->assertTrue(app()->environment('production'), 'Prémisse : environnement de production.');
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

    private function normaliser(string $texte): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texte));
    }

    private function requete(string $methode, string $uri, string $locale): TestResponse
    {
        $this->withSession(['locale' => $locale]);

        return $methode === 'POST' ? $this->post($uri, self::SAISIE) : $this->get($uri);
    }

    /**
     * Pages éditoriales représentatives et pages du calculateur.
     *
     * @return array<string, array{string, string, string, string}>
     */
    public static function pages(): array
    {
        return [
            'accueil' => ['GET', '/', 'fr', 'home'],
            'accueil ar' => ['GET', '/', 'ar', 'home'],
            'documentation' => ['GET', '/documentation', 'fr', 'documentation'],
            'documentation api' => ['GET', '/api-documentation', 'en', 'api.documentation'],
            'fiabilite' => ['GET', '/fiabilite', 'fr', 'trust'],
            'guide salaire net' => ['GET', '/calcul-salaire-net-maroc', 'es', 'guides.calcul-salaire-net-maroc'],
            'calculateur formulaire' => ['GET', '/calculateur', 'fr', 'calculator.index'],
            'calculateur resultat' => ['POST', '/calculateur/calculer', 'fr', 'calculator.calculer'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pagesCsp(): array
    {
        return [
            'accueil' => ['/'],
            'documentation' => ['/documentation'],
            'fiabilite' => ['/fiabilite'],
            'calculateur' => ['/calculateur'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['fr' => ['fr'], 'en' => ['en'], 'ar' => ['ar'], 'es' => ['es']];
    }

    // ------------------------------------------------------------------
    // 1. Aucune balise AdSense servie
    // ------------------------------------------------------------------

    /** Garde-fou : la page est rendue normalement, indépendamment de la publicité. */
    #[DataProvider('pages')]
    public function test_la_page_reste_rendue_avec_son_identite(string $methode, string $uri, string $locale, string $route): void
    {
        $this->activerAncienneConfigPublicitaire();

        $response = $this->requete($methode, $uri, $locale);

        $response->assertOk();
        $this->assertTrue(request()->routeIs($route), "{$uri} : rendu par la route {$route}.");

        $xpath = $this->dom($response->getContent());
        $this->assertSame(1, $xpath->query('/html[@lang="'.$locale.'"]')->length, "{$uri} : document html en {$locale}.");
        $this->assertSame(1, $xpath->query('//main[@id="main-content"]')->length, "{$uri} : contenu principal rendu.");
        $this->assertNotSame('', $this->normaliser((string) $xpath->query('//head/title')->item(0)?->textContent), "{$uri} : titre de page rendu.");
    }

    #[DataProvider('pages')]
    public function test_aucun_script_emplacement_ni_initialisation_adsense(string $methode, string $uri, string $locale, string $route): void
    {
        $this->activerAncienneConfigPublicitaire();

        // Statut vérifié d'abord : une page en erreur ne vaut pas absence de publicité.
        $html = $this->requete($methode, $uri, $locale)->assertOk()->getContent();
        $this->assertTrue(request()->routeIs($route), "Prémisse : {$uri} rendu par {$route}.");

        $xpath = $this->dom($html);
        $this->assertSame(
            0,
            $xpath->query('//script[contains(@src, "googlesyndication") or contains(@src, "adsbygoogle") or contains(@src, "doubleclick") or contains(@src, "googletagservices")]')->length,
            "{$uri} : aucun script réseau AdSense",
        );
        $this->assertSame(0, $xpath->query('//ins')->length, "{$uri} : aucun emplacement ins");
        $this->assertSame(0, $xpath->query('//*[@data-ad-client or @data-ad-slot]')->length, "{$uri} : aucun attribut data-ad-*");
        $this->assertSame(0, $xpath->query('//aside[contains(concat(" ", normalize-space(@class), " "), " ad-slot ")]')->length, "{$uri} : aucun conteneur ad-slot");

        foreach ([...self::MARQUEURS_PUBLICITAIRES, self::SLOT_HEADER, self::SLOT_FOOTER] as $motif) {
            $this->assertStringNotContainsString($motif, $html, "{$uri} : aucun marqueur publicitaire ({$motif}), initialisation comprise");
        }
    }

    #[DataProvider('pages')]
    public function test_aucune_meta_de_verification_ni_identifiant_adsense(string $methode, string $uri, string $locale, string $route): void
    {
        $this->activerAncienneConfigPublicitaire();

        $html = $this->requete($methode, $uri, $locale)->assertOk()->getContent();
        $this->assertTrue(request()->routeIs($route), "Prémisse : {$uri} rendu par {$route}.");

        $this->assertSame(0, $this->dom($html)->query('//meta[@name="google-adsense-account"]')->length, "{$uri} : aucune meta google-adsense-account");
        $this->assertStringNotContainsString('google-adsense-account', $html, "{$uri} : aucune vérification AdSense");
        $this->assertStringNotContainsString(self::PUBLISHER, $html, "{$uri} : identifiant éditeur absent, meta comprise");
    }

    // ------------------------------------------------------------------
    // 2. CSP sans hôte publicitaire, /ads.txt retiré
    // ------------------------------------------------------------------

    #[DataProvider('pagesCsp')]
    public function test_la_csp_ne_declare_aucun_hote_publicitaire(string $uri): void
    {
        $this->activerAncienneConfigPublicitaire();

        $csp = (string) $this->get($uri)->assertOk()->headers->get('Content-Security-Policy');
        $this->assertNotSame('', $csp, "Prémisse : {$uri} envoie une CSP.");

        foreach (self::MARQUEURS_PUBLICITAIRES as $hote) {
            $this->assertStringNotContainsString($hote, $csp, "{$uri} : CSP sans hôte publicitaire ({$hote})");
        }
    }

    /** Garde-fou : les CDN non publicitaires requis restent autorisés. */
    #[DataProvider('pagesCsp')]
    public function test_la_csp_garde_les_cdn_requis(string $uri): void
    {
        $this->activerAncienneConfigPublicitaire();

        $csp = (string) $this->get($uri)->assertOk()->headers->get('Content-Security-Policy');

        $directives = [];
        foreach (explode(';', $csp) as $directive) {
            $morceaux = preg_split('/\s+/', trim($directive));
            if ($morceaux !== false && $morceaux[0] !== '') {
                $directives[array_shift($morceaux)] = $morceaux;
            }
        }

        $this->assertContains('https://cdn.jsdelivr.net', $directives['script-src'] ?? [], "{$uri} : script-src garde cdn.jsdelivr.net");
        $this->assertContains('https://cdn.jsdelivr.net', $directives['style-src'] ?? [], "{$uri} : style-src garde cdn.jsdelivr.net");
        $this->assertContains('https://fonts.googleapis.com', $directives['style-src'] ?? [], "{$uri} : style-src garde fonts.googleapis.com");
        $this->assertContains('https://fonts.gstatic.com', $directives['font-src'] ?? [], "{$uri} : font-src garde fonts.gstatic.com");
    }

    public function test_ads_txt_n_est_plus_une_route(): void
    {
        $this->activerAncienneConfigPublicitaire();

        foreach (Route::getRoutes() as $route) {
            $this->assertNotSame('ads.txt', ltrim($route->uri(), '/'), 'Aucune route /ads.txt déclarée.');
        }

        $response = $this->get('/ads.txt');
        $response->assertNotFound();
        $this->assertStringNotContainsString(self::PUBLISHER, (string) $response->getContent(), '/ads.txt : aucun identifiant éditeur servi.');
    }

    // ------------------------------------------------------------------
    // 3. /fiabilite : confidentialité sincère, quatre locales
    // ------------------------------------------------------------------

    #[DataProvider('locales')]
    public function test_fiabilite_affiche_le_texte_de_confidentialite_de_reference(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])->get('/fiabilite')->assertOk()->getContent();
        $xpath = $this->dom($html);
        $this->assertSame(1, $xpath->query('/html[@lang="'.$locale.'"]')->length, "Prémisse : /fiabilite rendue en {$locale}.");

        $attendu = self::TEXTES_CONFIDENTIALITE[$locale];
        $trouves = [];
        foreach ($xpath->query('//body//*[not(self::script) and not(self::style) and not(ancestor::script)]') as $element) {
            if ($this->normaliser($element->textContent) === $attendu) {
                $trouves[] = $element;
            }
        }

        $this->assertNotEmpty($trouves, "/fiabilite ({$locale}) : paragraphe de confidentialité de référence affiché à l'identique.");

        if ($locale === 'ar') {
            foreach ($trouves as $element) {
                $this->assertSame(0, $xpath->query('ancestor-or-self::*[@dir="ltr"]', $element)->length, '/fiabilite (ar) : paragraphe hors de tout bloc ltr.');
            }
        }
    }

    #[DataProvider('locales')]
    public function test_fiabilite_sans_intitule_de_confidentialite_absolue(string $locale): void
    {
        $html = $this->withSession(['locale' => $locale])->get('/fiabilite')->assertOk()->getContent();
        $xpath = $this->dom($html);
        $this->assertSame(1, $xpath->query('/html[@lang="'.$locale.'"]')->length, "Prémisse : /fiabilite rendue en {$locale}.");

        [$intitule, $racine] = self::INTITULES_ABSOLUS[$locale];
        $this->assertFalse(mb_stripos($html, $intitule) !== false, "/fiabilite ({$locale}) : intitulé « {$intitule} » absent.");

        foreach ($xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //nav//a | //aside//a') as $titre) {
            $texte = $this->normaliser($titre->textContent);
            $this->assertFalse(mb_stripos($texte, $racine) !== false, "/fiabilite ({$locale}) : intitulé sans confidentialité absolue ({$texte}).");
        }
    }

    /** Garde-fou : la page de fiabilité reste en RTL pour l'arabe. */
    public function test_fiabilite_conserve_le_rtl_en_arabe(): void
    {
        $html = $this->withSession(['locale' => 'ar'])->get('/fiabilite')->assertOk()->getContent();

        $this->assertSame(1, $this->dom($html)->query('/html[@lang="ar"][@dir="rtl"]')->length, '/fiabilite (ar) : document en RTL.');
    }
}
