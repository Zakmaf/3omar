<?php

namespace Tests\Feature;

use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Retours sans référent avec plusieurs onglets dans la même session (issue #295).
 *
 * Les routes calculator.* sont servies sous Referrer-Policy: no-referrer (#283).
 * Avant la correction, le retour après une erreur de validation et le retour
 * après un changement de langue se repliaient sur _previous.url, la dernière
 * URL GET de la session, partagée par tous les onglets : une page consultée
 * dans l'onglet B détournait le retour de l'onglet A (preuve RED : PR #296,
 * run 44269519466). Désormais, la page d'origine transmet sa cible : champ
 * caché du formulaire, paramètre des liens de langue, revalidés et
 * reconstruits côté serveur. Ces tests ne portent que sur le contrat HTTP et
 * le HTML rendu, sans dépendre d'aucune classe de production nouvelle.
 *
 * Scénario commun : GET de la page A, GET d'une page B dans la même session,
 * puis action depuis A (soumission du formulaire réellement rendu dans A, ou
 * lien de langue réellement rendu dans A). Aucune requête n'envoie d'en-tête
 * Referer (ni from()), comme un navigateur sous no-referrer, sauf le test de
 * compatibilité du référent, qui le dit.
 *
 * Les garde-fous de sécurité visent le vrai paramètre retenu,
 * self::PARAMETRE_RETOUR, avec une matrice de vecteurs : aucune
 * redirection hors du site, ni vers une route non rejouable, ni vers la page
 * d'un autre onglet.
 */
class RetourMultiOngletsTest extends TestCase
{
    private const SAISIE_A = ['mode' => 'gross_to_net', 'salaire_base' => 12345, 'type_frais_pro' => 'commun'];

    private const SAISIE_B = ['mode' => 'gross_to_net', 'salaire_base' => 13579, 'type_frais_pro' => 'commun', 'exercice' => 2027];

    private const SAISIE_GAIN = ['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun'];

    private const SAISIE_NET = ['mode' => 'net_to_gross', 'net_cible' => 9876, 'type_frais_pro' => 'commun'];

    /**
     * Saisie invalide (salaire de base manquant) accompagnée de montants,
     * envoyée comme un formulaire HTML (chaînes).
     */
    private const SAISIE_INVALIDE = [
        'mode' => 'gross_to_net',
        'type_frais_pro' => 'commun',
        'prime_bilan' => '0',
        'prime_rendement' => '4321',
        'retenues_imposees_ir' => '55',
    ];

    /**
     * Contrat HTTP (#295) : nom du champ caché du formulaire et du paramètre
     * des liens de langue. Littéral volontaire : le test ne dépend d'aucune
     * classe de production.
     */
    private const PARAMETRE_RETOUR = 'retour';

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

    private function payload(array $saisie): string
    {
        $payload = $this->codec->encode($saisie);
        $this->assertNotNull($this->codec->decode($payload), 'Prémisse : payload valide.');

        return $payload;
    }

    /** Remplace {a}, {b} et {g} par des payloads valides. */
    private function resoudre(string $gabarit): string
    {
        return strtr($gabarit, [
            '{a}' => $this->payload(self::SAISIE_A),
            '{b}' => $this->payload(self::SAISIE_B),
            '{g}' => $this->payload(self::SAISIE_GAIN),
        ]);
    }

    private function getSansReferent(string $uri): TestResponse
    {
        $response = $this->get($uri);
        $this->assertNull(request()->headers->get('referer'), "Prémisse : aucun référent envoyé ({$uri}).");

        return $response;
    }

    private function postSansReferent(string $uri, array $donnees): TestResponse
    {
        $response = $this->post($uri, $donnees);
        $this->assertNull(request()->headers->get('referer'), "Prémisse : aucun référent envoyé ({$uri}).");

        return $response;
    }

    /** Chemin et paramètres de requête d'une URL, indépendamment de leur ordre. */
    private function cheminEtRequete(string $url): array
    {
        $parties = parse_url($url);
        parse_str($parties['query'] ?? '', $query);
        ksort($query);

        return [$parties['path'] ?? '/', $query];
    }

    private function location(TestResponse $response): string
    {
        return (string) $response->headers->get('Location');
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

    /** Valeur de l'unique champ du formulaire portant ce nom (et ce type, si précisé). */
    private function valeurChamp(\DOMXPath $xpath, string $nom, ?string $type = null): string
    {
        $filtre = $type === null ? '' : '[@type="'.$type.'"]';
        $champs = $xpath->query('//form[@id="payrollForm"]//input[@name="'.$nom.'"]'.$filtre);
        $this->assertSame(1, $champs->length, "Un seul champ {$nom}.");

        return $champs->item(0)->getAttribute('value');
    }

    /**
     * Soumet le formulaire du calculateur réellement rendu dans une page :
     * même cible (action) et mêmes champs cachés, complétés puis remplacés par
     * la saisie. Aucun champ caché futur n'est supposé ni omis.
     */
    private function soumettre(string $html, array $saisie): TestResponse
    {
        $xpath = $this->dom($html);
        $formulaires = $xpath->query('//form[@id="payrollForm"]');
        $this->assertSame(1, $formulaires->length, 'Un seul formulaire du calculateur.');
        $this->assertSame('post', strtolower($formulaires->item(0)->getAttribute('method')), 'Formulaire envoyé en POST.');

        $action = $formulaires->item(0)->getAttribute('action');
        $this->assertSame(
            parse_url(url('/'), PHP_URL_HOST),
            parse_url(url($action), PHP_URL_HOST),
            'Formulaire soumis au site.',
        );

        $caches = [];
        foreach ($xpath->query('//form[@id="payrollForm"]//input[@type="hidden"][@name]') as $champ) {
            $caches[$champ->getAttribute('name')] = $champ->getAttribute('value');
        }

        return $this->postSansReferent($action, array_merge($caches, $saisie));
    }

    /** Langue du document rendu (attribut lang de l'élément html). */
    private function langueDocument(string $html): string
    {
        $attributs = $this->dom($html)->query('//html/@lang');
        $this->assertSame(1, $attributs->length, 'Un seul élément html.');

        return $attributs->item(0)->nodeValue;
    }

    /**
     * Lien de langue réellement rendu par le sélecteur de la page : aucune
     * forme d'URL n'est supposée, seul le lien existant est suivi.
     */
    private function lienDeLangue(string $html, string $langue): string
    {
        $liens = $this->dom($html)->query(
            '//*[contains(concat(" ", normalize-space(@class), " "), " language-switcher ")]//a[@hreflang="'.$langue.'"]',
        );
        $this->assertSame(1, $liens->length, "Un seul lien de langue {$langue} dans le sélecteur.");

        $href = $liens->item(0)->getAttribute('href');
        $this->assertNotSame('', $href, "Lien de langue {$langue} non vide.");
        $this->assertSame(
            parse_url(url('/'), PHP_URL_HOST),
            parse_url(url($href), PHP_URL_HOST),
            'Le lien de langue reste sur le site.',
        );

        return $href;
    }

    /** Redirection sur le site, sans aucune trace de la cible malveillante. */
    private function assertRedirectionSure(TestResponse $response, string $ou): string
    {
        $this->assertTrue($response->isRedirect(), "{$ou} : redirection attendue, HTTP {$response->getStatusCode()}.");
        $location = $this->location($response);

        $this->assertMatchesRegularExpression('#^https?://#i', $location, "{$ou} : Location absolue http(s), jamais un autre schéma.");
        $this->assertSame(parse_url(url('/'), PHP_URL_HOST), parse_url($location, PHP_URL_HOST), "{$ou} : même hôte.");
        $this->assertNull(parse_url($location, PHP_URL_USER), "{$ou} : aucune information d'utilisateur dans la cible.");
        $this->assertStringNotContainsStringIgnoringCase('evil.example', rawurldecode($location), "{$ou} : cible malveillante écartée.");

        $chemin = parse_url($location, PHP_URL_PATH) ?: '/';
        $this->assertStringStartsWith('/', $chemin, "{$ou} : chemin absolu.");
        $this->assertStringNotContainsString('\\', rawurldecode($chemin), "{$ou} : aucune barre oblique inverse.");
        $this->assertStringStartsNotWith('//', rawurldecode($chemin), "{$ou} : aucun chemin interprétable comme protocole relatif.");

        return $location;
    }

    /** Chemin d'une cible interne qui ne doit jamais servir de retour. */
    private function assertCheminRejouable(string $location, string $ou): void
    {
        $chemin = parse_url($location, PHP_URL_PATH) ?: '/';

        $this->assertStringStartsNotWith('/lang/', $chemin, "{$ou} : jamais une boucle de changement de langue.");
        $this->assertStringStartsNotWith('/api/', $chemin, "{$ou} : jamais une route d'API.");
        $this->assertNotSame('/up', $chemin, "{$ou} : jamais la sonde de santé.");
        $this->assertNotSame('/calculateur/calculer', $chemin, "{$ou} : jamais la page résultat (POST), non rejouable.");
    }

    /**
     * Le formulaire de retour affiche les erreurs et la saisie de l'utilisateur.
     * Les primes imposables sont regroupées par conception dans le seul champ
     * visible autres_primes (somme de prime_bilan, prime_rendement et
     * autres_primes) ; les deux premières restent des champs cachés à 0, sans
     * double comptage (voir CalculateurReferentTest).
     */
    private function assertFormulaireConserve(string $location, ?string $comparaisonA = null): void
    {
        $response = $this->getSansReferent($location)->assertOk();
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'), 'Formulaire servi sous no-referrer.');

        $xpath = $this->dom($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@id="formErrors"]')->length, 'Erreurs de validation affichées.');
        $this->assertSame(4321.0, (float) $this->valeurChamp($xpath, 'autres_primes'), 'Prime conservée dans le champ des primes imposables.');
        $this->assertSame('0', $this->valeurChamp($xpath, 'prime_rendement', 'hidden'), 'Pas de double comptage de la prime.');
        $this->assertSame('0', $this->valeurChamp($xpath, 'prime_bilan', 'hidden'), 'Pas de double comptage de la prime.');
        $this->assertSame(55.0, (float) $this->valeurChamp($xpath, 'retenues_imposees_ir'), 'Retenue conservée.');

        if ($comparaisonA !== null) {
            $this->assertSame($comparaisonA, $this->valeurChamp($xpath, 'comparer_a', 'hidden'), 'Scénario A de la comparaison conservé.');
        }
    }

    // ------------------------------------------------------------------
    // Validation : retour au formulaire de l'onglet A (ROUGE au HEAD)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function validationsMultiOnglets(): array
    {
        return [
            'formulaire vide puis documentation' => ['/calculateur', '/documentation'],
            'formulaire restauré puis documentation' => ['/calculateur?s={a}', '/documentation'],
            'formulaire restauré puis guide' => ['/calculateur?s={a}', '/calcul-salaire-net-maroc'],
            'profil puis fiabilité' => ['/calculateur?profil=standard', '/fiabilite'],
            'formulaire restauré puis autre simulation' => ['/calculateur?s={a}', '/calculateur?s={b}'],
            'formulaire restauré puis comparaison' => ['/calculateur?s={a}', '/calculateur/comparer?a={a}&b={b}'],
        ];
    }

    #[DataProvider('validationsMultiOnglets')]
    public function test_une_erreur_de_validation_apres_un_autre_onglet_revient_au_formulaire_d_origine(string $depart, string $autreOnglet): void
    {
        $depart = $this->resoudre($depart);
        $formulaire = $this->getSansReferent($depart)->assertOk()->getContent();
        $this->getSansReferent($this->resoudre($autreOnglet))->assertOk();

        $response = $this->soumettre($formulaire, self::SAISIE_INVALIDE);

        $location = $this->assertRedirectionSure($response, 'retour de validation');
        $this->assertSame(
            $this->cheminEtRequete(url($depart)),
            $this->cheminEtRequete($location),
            'Retour au formulaire de l\'onglet A, jamais à la page de l\'autre onglet.',
        );
        $response->assertSessionHasErrors('salaire_base');
        $response->assertSessionHasInput('prime_rendement', '4321');
        $response->assertSessionHasInput('retenues_imposees_ir', '55');

        $this->assertFormulaireConserve($location);
    }

    public function test_une_erreur_de_validation_en_comparaison_apres_un_autre_onglet_garde_le_scenario_a(): void
    {
        $a = $this->payload(self::SAISIE_A);
        $depart = '/calculateur?s='.$a.'&a='.$a;

        $formulaire = $this->getSansReferent($depart)->assertOk();
        $champA = $this->valeurChamp($this->dom($formulaire->getContent()), 'comparer_a', 'hidden');
        $this->assertSame($a, $champA, 'Prémisse : le formulaire arme la comparaison.');

        $this->getSansReferent('/documentation')->assertOk();

        $response = $this->soumettre($formulaire->getContent(), self::SAISIE_INVALIDE);

        $location = $this->assertRedirectionSure($response, 'retour de validation en comparaison');
        $this->assertSame(
            $this->cheminEtRequete(url($depart)),
            $this->cheminEtRequete($location),
            'Retour au formulaire de comparaison de l\'onglet A.',
        );
        $response->assertSessionHasErrors('salaire_base');

        $this->assertFormulaireConserve($location, $a);
    }

    public function test_une_erreur_de_validation_sans_navigation_prealable_revient_au_calculateur(): void
    {
        // Session neuve, aucun GET préalable, aucun référent : seul le
        // formulaire du calculateur est un repli sûr, jamais l'accueil.
        $response = $this->postSansReferent('/calculateur/calculer', self::SAISIE_INVALIDE);

        $location = $this->assertRedirectionSure($response, 'retour de validation sans historique');
        [$chemin] = $this->cheminEtRequete($location);
        $this->assertSame('/calculateur', $chemin, 'Repli sur le formulaire du calculateur.');
        $response->assertSessionHasErrors('salaire_base');

        $this->assertFormulaireConserve($location);
    }

    // ------------------------------------------------------------------
    // Langue : retour à la page de l'onglet A (ROUGE au HEAD)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function languesMultiOnglets(): array
    {
        return [
            'formulaire vide puis documentation' => ['/calculateur', '/documentation', 'en'],
            'formulaire restauré puis documentation' => ['/calculateur?s={a}', '/documentation', 'en'],
            'formulaire en comparaison puis guide' => ['/calculateur?s={a}&a={a}', '/calcul-salaire-net-maroc', 'ar'],
            'profil puis fiabilité' => ['/calculateur?profil=standard', '/fiabilite', 'es'],
            'comparaison puis documentation' => ['/calculateur/comparer?a={a}&b={b}', '/documentation', 'en'],
            'gain 2027 puis documentation' => ['/calculateur/gain-2027?s={g}', '/documentation', 'ar'],
            'formulaire restauré puis autre simulation' => ['/calculateur?s={a}', '/calculateur?s={b}', 'en'],
            'documentation puis simulation' => ['/documentation', '/calculateur?s={a}', 'en'],
            'guide puis comparaison' => ['/calcul-salaire-net-maroc', '/calculateur/comparer?a={a}&b={b}', 'ar'],
            'accueil puis fiabilité' => ['/', '/fiabilite', 'es'],
        ];
    }

    #[DataProvider('languesMultiOnglets')]
    public function test_le_lien_de_langue_de_l_onglet_a_revient_a_sa_page_apres_un_autre_onglet(string $pageA, string $pageB, string $langue): void
    {
        $pageA = $this->resoudre($pageA);
        $lien = $this->lienDeLangue($this->getSansReferent($pageA)->assertOk()->getContent(), $langue);
        $this->getSansReferent($this->resoudre($pageB))->assertOk();

        $response = $this->getSansReferent($lien);

        $location = $this->assertRedirectionSure($response, 'retour de langue');
        $response->assertSessionHas('locale', $langue);
        $this->assertSame(
            $this->cheminEtRequete(url($pageA)),
            $this->cheminEtRequete($location),
            'Retour à la page de l\'onglet A, payload compris, jamais à la page de l\'autre onglet.',
        );

        $arrivee = $this->getSansReferent($location)->assertOk();
        $this->assertSame($langue, $this->langueDocument($arrivee->getContent()), 'Langue appliquée.');

        $calculateur = str_starts_with(parse_url($location, PHP_URL_PATH) ?: '/', '/calculateur');
        $this->assertSame(
            $calculateur ? 'no-referrer' : 'strict-origin-when-cross-origin',
            $arrivee->headers->get('Referrer-Policy'),
            'Politique de référent de la page d\'arrivée inchangée.',
        );
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function resultatsPostes(): array
    {
        return [
            'brut vers net, même onglet' => ['brut', false],
            'brut vers net, après un autre onglet' => ['brut', true],
            'net cible, après un autre onglet' => ['net', true],
        ];
    }

    /**
     * La page résultat répond à un POST : elle n'est pas rejouable (un GET
     * renvoie au formulaire avec une notice). Le changement de langue ramène
     * au calculateur rechargé avec la saisie, par son lien partagé.
     */
    #[DataProvider('resultatsPostes')]
    public function test_le_lien_de_langue_d_un_resultat_ramene_au_calculateur_rejouable_avec_la_saisie(string $mode, bool $autreOnglet): void
    {
        $saisie = $mode === 'net' ? self::SAISIE_NET : self::SAISIE_A;

        $formulaire = $this->getSansReferent('/calculateur')->assertOk()->getContent();
        $resultat = $this->soumettre($formulaire, $saisie)->assertOk();
        $partage = $resultat->viewData('share_payload');
        $this->assertIsString($partage, 'Prémisse : la page résultat porte un lien partagé.');
        $lien = $this->lienDeLangue($resultat->getContent(), 'en');

        if ($autreOnglet) {
            $this->getSansReferent('/documentation')->assertOk();
        }

        $response = $this->getSansReferent($lien);

        $location = $this->assertRedirectionSure($response, 'langue depuis le résultat');
        $this->assertCheminRejouable($location, 'langue depuis le résultat');
        $response->assertSessionHas('locale', 'en');
        [$chemin, $query] = $this->cheminEtRequete($location);
        $this->assertSame('/calculateur', $chemin, 'Calculateur rejouable, jamais la page de l\'autre onglet.');
        $this->assertArrayHasKey('s', $query, 'Saisie rechargée par le lien partagé.');
        $this->assertSame($this->codec->decode($partage), $this->codec->decode((string) $query['s']), 'Même saisie que le lien partagé du résultat.');

        $arrivee = $this->getSansReferent($location)->assertOk();
        $this->assertSame('en', $this->langueDocument($arrivee->getContent()));
        $xpath = $this->dom($arrivee->getContent());
        $champ = $mode === 'net' ? 'net_cible' : 'salaire_base';
        $this->assertSame((float) $saisie[$champ], (float) $this->valeurChamp($xpath, $champ), 'Saisie rechargée dans le formulaire.');
        $this->assertSame(
            $saisie['mode'],
            $xpath->query('//form[@id="payrollForm"]//input[@name="mode"][@checked]/@value')->item(0)?->nodeValue,
            'Mode de calcul rechargé.',
        );
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function autreOnglet(): array
    {
        return [
            'même onglet' => [false],
            'après un autre onglet' => [true],
        ];
    }

    /**
     * Une comparaison obtenue par POST (formulaire armé avec le scénario A)
     * a déjà une URL rejouable, son lien de partage : le changement de langue
     * y ramène.
     */
    #[DataProvider('autreOnglet')]
    public function test_le_lien_de_langue_d_une_comparaison_postee_ramene_a_la_comparaison_rejouable(bool $autreOnglet): void
    {
        $a = $this->payload(self::SAISIE_A);
        $formulaire = $this->getSansReferent('/calculateur?s='.$a.'&a='.$a)->assertOk()->getContent();

        $comparaison = $this->soumettre($formulaire, self::SAISIE_B)->assertOk();
        $payloadA = $comparaison->viewData('payload_a');
        $payloadB = $comparaison->viewData('payload_b');
        $this->assertIsString($payloadA, 'Prémisse : comparaison rendue.');
        $this->assertIsString($payloadB, 'Prémisse : comparaison rendue.');
        $lien = $this->lienDeLangue($comparaison->getContent(), 'ar');

        if ($autreOnglet) {
            $this->getSansReferent('/documentation')->assertOk();
        }

        $response = $this->getSansReferent($lien);

        $location = $this->assertRedirectionSure($response, 'langue depuis la comparaison postée');
        $this->assertCheminRejouable($location, 'langue depuis la comparaison postée');
        $this->assertSame(
            $this->cheminEtRequete(route('calculator.comparer', ['a' => $payloadA, 'b' => $payloadB])),
            $this->cheminEtRequete($location),
            'Retour à l\'URL partageable de la comparaison.',
        );

        $arrivee = $this->getSansReferent($location)->assertOk();
        $this->assertSame('ar', $this->langueDocument($arrivee->getContent()));
        $this->assertSame('no-referrer', $arrivee->headers->get('Referrer-Policy'));
    }

    // ------------------------------------------------------------------
    // Paramètre de retour : forme rendue et confidentialité
    // ------------------------------------------------------------------

    public function test_le_formulaire_et_les_liens_de_langue_portent_la_cible_de_leur_page(): void
    {
        $a = $this->payload(self::SAISIE_A);
        $page = '/calculateur?s='.$a.'&a='.$a;
        $html = $this->getSansReferent($page)->assertOk()->getContent();

        $this->assertSame($page, $this->valeurChamp($this->dom($html), self::PARAMETRE_RETOUR, 'hidden'), 'Champ caché : cible du formulaire.');

        foreach (array_keys(config('app.supported_locales')) as $langue) {
            parse_str((string) parse_url($this->lienDeLangue($html, $langue), PHP_URL_QUERY), $query);
            $this->assertSame([self::PARAMETRE_RETOUR => $page], $query, "Lien {$langue} : seule la cible de la page.");
        }
    }

    /**
     * Une page éditoriale (servie sans no-referrer) ne recopie
     * jamais un payload de sa propre URL dans ses liens de langue.
     */
    public function test_le_lien_de_langue_d_une_page_editoriale_ne_porte_aucun_payload(): void
    {
        $a = $this->payload(self::SAISIE_A);

        foreach (['/' => '/', '/documentation' => '/documentation', '/fiabilite' => '/fiabilite', '/calcul-salaire-net-maroc' => '/calcul-salaire-net-maroc'] as $page => $attendue) {
            $html = $this->getSansReferent($page.'?s='.$a.'&a='.$a.'&b='.$a)->assertOk()->getContent();
            $lien = $this->lienDeLangue($html, 'en');

            $this->assertStringNotContainsString($a, rawurldecode($lien), "{$page} : aucun payload dans le lien de langue.");
            parse_str((string) parse_url($lien, PHP_URL_QUERY), $query);
            $this->assertSame([self::PARAMETRE_RETOUR => $attendue], $query, "{$page} : cible sans paramètre.");
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ciblesRejouables(): array
    {
        return [
            'formulaire, paramètre étranger retiré' => ['/calculateur?s={a}&a={a}&profil=standard&x=1', '/calculateur?s={a}&a={a}&profil=standard'],
            'formulaire, valeur hors alphabet retirée' => ['/calculateur?s=%3Cscript%3E&profil=standard', '/calculateur?profil=standard'],
            'formulaire, valeur tableau retirée' => ['/calculateur?s[]={a}', '/calculateur'],
            'formulaire, barre finale' => ['/calculateur/?s={a}', '/calculateur?s={a}'],
            'comparaison' => ['/calculateur/comparer?a={a}&b={b}&s={a}', '/calculateur/comparer?a={a}&b={b}'],
            'gain 2027' => ['/calculateur/gain-2027?s={g}', '/calculateur/gain-2027?s={g}'],
            'documentation, payload retiré' => ['/documentation?s={a}', '/documentation'],
            'guide' => ['/calcul-salaire-net-maroc', '/calcul-salaire-net-maroc'],
            'fiabilité' => ['/fiabilite', '/fiabilite'],
            'documentation API' => ['/api-documentation', '/api-documentation'],
            'accueil' => ['/', '/'],
        ];
    }

    #[DataProvider('ciblesRejouables')]
    public function test_une_cible_de_langue_rejouable_est_reconstruite_sans_parametre_etranger(string $cible, string $attendue): void
    {
        $response = $this->getSansReferent('/lang/fr?'.http_build_query([self::PARAMETRE_RETOUR => $this->resoudre($cible)]));

        $location = $this->assertRedirectionSure($response, 'cible rejouable');
        $this->assertSame($this->cheminEtRequete(url($this->resoudre($attendue))), $this->cheminEtRequete($location));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    // ------------------------------------------------------------------
    // Garde-fous du paramètre de retour : vecteurs malveillants
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function ciblesMalveillantes(): array
    {
        return [
            'URL externe' => ['https://evil.example/piege'],
            'URL externe en majuscules' => ['HTTPS://EVIL.EXAMPLE/piege'],
            'protocole relatif' => ['//evil.example/piege'],
            'protocole relatif avec utilisateur' => ['//localhost@evil.example/piege'],
            'barre oblique inverse' => ['/\\evil.example/piege'],
            'barres obliques inverses' => ['\\\\evil.example/piege'],
            'barres encodées' => ['/%2F%2Fevil.example/piege'],
            'barres doublement encodées' => ['/%252F%252Fevil.example/piege'],
            'barre inverse encodée' => ['/%5Cevil.example/piege'],
            'barre inverse doublement encodée' => ['/%255Cevil.example/piege'],
            'information d\'utilisateur' => ['http://localhost@evil.example/piege'],
            'schéma sans barres' => ['https:evil.example/piege'],
            'schéma javascript' => ['javascript:alert(1)//evil.example'],
            'schéma data' => ['data:text/html,evil.example'],
            'tabulation initiale' => ["\t//evil.example/piege"],
            'espace initiale' => [' //evil.example/piege'],
            'tabulation encodée' => ['/%09/evil.example/piege'],
            'caractère nul' => ["/calculateur\0//evil.example"],
            'retour chariot' => ["/calculateur\r\nLocation: https://evil.example"],
            'retour chariot encodé' => ['/calculateur%0D%0ALocation:%20https://evil.example'],
            'longueur excessive' => ['/calculateur?s='.str_repeat('A', 9000).'&x=evil.example'],
        ];
    }

    /**
     * Lien de langue explicite : une cible refusée revient à l'accueil, sans
     * jamais consulter l'historique de la session (ici la page d'un autre
     * onglet).
     */
    #[DataProvider('ciblesMalveillantes')]
    public function test_une_cible_de_langue_malveillante_revient_a_l_accueil_sans_historique(string $cible): void
    {
        $this->getSansReferent('/calculateur?s='.$this->payload(self::SAISIE_B))->assertOk();

        $response = $this->getSansReferent('/lang/en?'.http_build_query([self::PARAMETRE_RETOUR => $cible]));

        $location = $this->assertRedirectionSure($response, 'langue');
        $this->assertCheminRejouable($location, 'langue');
        $this->assertSame(['/', []], $this->cheminEtRequete($location), 'Repli sur l\'accueil, jamais l\'historique.');
        $response->assertSessionHas('locale', 'en');
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    #[DataProvider('ciblesMalveillantes')]
    public function test_une_cible_de_formulaire_malveillante_revient_au_formulaire_vide(string $cible): void
    {
        $formulaire = $this->getSansReferent('/calculateur?s='.$this->payload(self::SAISIE_A))->assertOk()->getContent();
        $this->getSansReferent('/documentation')->assertOk();

        $response = $this->soumettre($formulaire, self::SAISIE_INVALIDE + [self::PARAMETRE_RETOUR => $cible]);

        $location = $this->assertRedirectionSure($response, 'validation');
        $this->assertCheminRejouable($location, 'validation');
        $this->assertSame(['/calculateur', []], $this->cheminEtRequete($location), 'Repli sur le formulaire vide, jamais l\'historique.');
        $response->assertSessionHasErrors('salaire_base');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ciblesInternesNonRejouables(): array
    {
        return [
            'boucle de langue' => ['/lang/fr'],
            'boucle de langue imbriquée' => ['/lang/en?retour=%2Fcalculateur'],
            'route d\'API' => ['/api/v1/health'],
            'paramètres de l\'API' => ['/api/v1/parametres'],
            'sonde de santé' => ['/up'],
            'page résultat POST' => ['/calculateur/calculer'],
            'route inconnue' => ['/inexistant'],
            'remontée de chemin' => ['/calculateur/../lang/fr'],
        ];
    }

    #[DataProvider('ciblesInternesNonRejouables')]
    public function test_une_cible_de_langue_non_rejouable_revient_a_l_accueil(string $cible): void
    {
        $response = $this->getSansReferent('/lang/en?'.http_build_query([self::PARAMETRE_RETOUR => $cible]));

        $location = $this->assertRedirectionSure($response, 'langue');
        $this->assertCheminRejouable($location, 'langue');
        $this->assertSame(['/', []], $this->cheminEtRequete($location));
    }

    /**
     * Le retour de validation ne vise que le formulaire du calculateur : une
     * autre route, même rejouable, est refusée.
     */
    #[DataProvider('ciblesInternesNonRejouables')]
    public function test_une_cible_de_formulaire_hors_formulaire_revient_au_formulaire_vide(string $cible): void
    {
        foreach ([$cible, '/documentation', '/calculateur/comparer?a=x&b=y', '/'] as $valeur) {
            $formulaire = $this->getSansReferent('/calculateur')->assertOk()->getContent();

            $response = $this->soumettre($formulaire, self::SAISIE_INVALIDE + [self::PARAMETRE_RETOUR => $valeur]);

            $location = $this->assertRedirectionSure($response, 'validation');
            $this->assertSame(['/calculateur', []], $this->cheminEtRequete($location), "{$valeur} : formulaire vide.");
        }
    }

    public function test_une_cible_tableau_ne_casse_ni_la_langue_ni_la_validation(): void
    {
        $langue = $this->getSansReferent('/lang/en?'.http_build_query([self::PARAMETRE_RETOUR => ['/calculateur']]));
        $this->assertSame(['/', []], $this->cheminEtRequete($this->assertRedirectionSure($langue, 'langue, tableau')));

        $formulaire = $this->getSansReferent('/calculateur?profil=standard')->assertOk()->getContent();
        $validation = $this->soumettre($formulaire, self::SAISIE_INVALIDE + [self::PARAMETRE_RETOUR => ['/calculateur?profil=standard']]);
        $this->assertSame(['/calculateur', []], $this->cheminEtRequete($this->assertRedirectionSure($validation, 'validation, tableau')));
    }

    /**
     * Compatibilité d'un client qui n'envoie pas le champ (ancien formulaire
     * en cache, client HTTP) : son référent du même hôte sert de repli, un
     * référent d'un autre hôte est ignoré. Seul test qui envoie un référent.
     */
    public function test_sans_champ_de_retour_le_referent_du_meme_hote_sert_de_repli(): void
    {
        $page = '/calculateur?s='.$this->payload(self::SAISIE_A);

        $meme = $this->from(url($page))->post('/calculateur/calculer', self::SAISIE_INVALIDE);
        $this->assertSame($this->cheminEtRequete(url($page)), $this->cheminEtRequete($this->assertRedirectionSure($meme, 'référent du même hôte')));

        $autre = $this->from('https://evil.example'.$page)->post('/calculateur/calculer', self::SAISIE_INVALIDE);
        $this->assertSame(['/calculateur', []], $this->cheminEtRequete($this->assertRedirectionSure($autre, 'référent étranger')));
    }

    public function test_une_langue_non_supportee_reste_introuvable(): void
    {
        $this->getSansReferent('/lang/xx')->assertNotFound();
        $this->getSansReferent('/lang/xx?retour=%2Fcalculateur')->assertNotFound();
    }

    public function test_sans_historique_le_lien_historique_de_langue_se_replie_sur_le_site(): void
    {
        // Lien historique sans cible, session neuve, aucun référent : repli sûr.
        $response = $this->getSansReferent('/lang/ar');

        $location = $this->assertRedirectionSure($response, 'langue sans historique');
        $this->assertCheminRejouable($location, 'langue sans historique');
        $response->assertSessionHas('locale', 'ar');
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }
}
