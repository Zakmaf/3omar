<?php

namespace Tests\Feature;

use App\Http\Requests\PayrollValidation;
use App\Services\SimulationCodec;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Compatibilité des retours de navigation (issue #295, revue de la PR #296).
 *
 * Trois contrats HTTP, testés sur les vraies routes, sans dépendre d'aucune
 * classe de production nouvelle (le nom du paramètre de retour est un
 * littéral, comme dans RetourMultiOngletsTest) :
 *
 * 1. Un POST invalide sur /calculateur/calculer envoyé par un client qui
 *    attend du JSON (Accept: application/json, ou XHR qui accepte tout type)
 *    reçoit, comme avant #295, un 422 JSON avec message et erreurs par champ,
 *    sans redirection ni saisie flashée. Le même POST envoyé par un
 *    formulaire HTML reste un 302 vers la cible de son onglet.
 *    Attendu : ROUGE sur l'implémentation indexée (302 au lieu de 422), VERT
 *    sur la base historique (validate()) ; le témoin HTML est VERT partout.
 *
 * 2. Lien de langue historique, sans paramètre de retour : la page précédente
 *    (en-tête Referer, à défaut _previous.url de la session) n'est suivie que
 *    si elle est de même origine que l'application (schéma http(s), hôte et
 *    port identiques, sans information d'utilisateur). Elle est alors réduite
 *    à son chemin interne puis revalidée et reconstruite comme une cible
 *    explicite : route GET rejouable, seuls ses paramètres utiles. Autre
 *    origine, route exclue (résultat POST, API, sonde, boucle de langue,
 *    route inconnue) : accueil. Un lien qui porte un retour explicite ignore
 *    toujours le référent et la session.
 *    Attendu : ROUGE sur l'implémentation indexée pour les autres ports,
 *    identifiants, schémas non HTTP, routes exclues et paramètres étrangers ;
 *    VERT pour les autres hôtes, la boucle de langue, les cibles déjà propres
 *    et la priorité du retour explicite.
 *
 * 3. Le champ de retour n'entre jamais dans un payload de partage.
 *    Attendu : VERT (garde-fou de non-régression).
 *
 * Fixtures synthétiques : hôte de l'application de test et evil.example.
 */
class RetourNavigationCompatibiliteTest extends TestCase
{
    private const SAISIE_A = ['mode' => 'gross_to_net', 'salaire_base' => 12345, 'type_frais_pro' => 'commun'];

    private const SAISIE_B = ['mode' => 'gross_to_net', 'salaire_base' => 13579, 'type_frais_pro' => 'commun', 'exercice' => 2027];

    private const SAISIE_GAIN = ['mode' => 'gross_to_net', 'salaire_base' => 20000, 'type_frais_pro' => 'commun'];

    /** Saisie sans salaire de base, envoyée comme un formulaire HTML (chaînes). */
    private const SAISIE_INVALIDE = [
        'mode' => 'gross_to_net',
        'type_frais_pro' => 'commun',
        'prime_rendement' => '4321',
        'retenues_imposees_ir' => '55',
    ];

    /** Contrat HTTP (#295), littéral volontaire. */
    private const PARAMETRE_RETOUR = 'retour';

    private const ENTETES_JSON = ['Accept' => 'application/json'];

    private const ENTETES_XHR = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*'];

    private const ENTETES_HTML = ['Accept' => 'text/html,application/xhtml+xml'];

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

    /** Remplace {origine}, {hote}, {a}, {b} et {g} par l'origine de test et des payloads valides. */
    private function resoudre(string $gabarit): string
    {
        return strtr($gabarit, [
            '{origine}' => rtrim(url('/'), '/'),
            '{hote}' => (string) parse_url(url('/'), PHP_URL_HOST),
            '{a}' => $this->payload(self::SAISIE_A),
            '{b}' => $this->payload(self::SAISIE_B),
            '{g}' => $this->payload(self::SAISIE_GAIN),
        ]);
    }

    /** Chemin et paramètres de requête d'une URL, indépendamment de leur ordre. */
    private function cheminEtRequete(string $url): array
    {
        $parties = parse_url($url);
        parse_str($parties['query'] ?? '', $query);
        ksort($query);

        return [$parties['path'] ?? '/', $query];
    }

    /**
     * Redirection vers l'origine même de l'application : schéma http(s), même
     * hôte, même port, sans identifiants ni trace de la cible étrangère.
     */
    private function assertRedirectionMemeOrigine(TestResponse $response, string $ou): string
    {
        $this->assertTrue($response->isRedirect(), "{$ou} : redirection attendue, HTTP {$response->getStatusCode()}.");
        $location = (string) $response->headers->get('Location');
        $application = parse_url(url('/'));

        $this->assertMatchesRegularExpression('#^https?://#i', $location, "{$ou} : Location absolue http(s).");
        $this->assertSame($application['host'] ?? null, parse_url($location, PHP_URL_HOST), "{$ou} : même hôte.");
        $this->assertSame($application['port'] ?? null, parse_url($location, PHP_URL_PORT), "{$ou} : même port.");
        $this->assertNull(parse_url($location, PHP_URL_USER), "{$ou} : aucune information d'utilisateur.");
        $this->assertNull(parse_url($location, PHP_URL_PASS), "{$ou} : aucun mot de passe.");
        $this->assertStringNotContainsStringIgnoringCase('evil.example', rawurldecode($location), "{$ou} : cible étrangère écartée.");

        return $location;
    }

    /**
     * Lien de langue historique, sans paramètre de retour. La page précédente
     * est portée soit par l'en-tête Referer, soit par _previous.url.
     */
    private function langueHistorique(string $langue, string $canal, ?string $precedente): TestResponse
    {
        if ($precedente !== null && $canal === 'session') {
            $this->withSession(['_previous' => ['url' => $precedente]]);
        }

        if ($precedente !== null && $canal === 'referent') {
            $this->withHeader('Referer', $precedente);
        }

        $response = $this->get('/lang/'.$langue);
        $this->assertFalse(request()->query->has(self::PARAMETRE_RETOUR), 'Prémisse : lien historique sans retour.');
        $this->assertSame(
            $canal === 'referent' ? $precedente : null,
            request()->headers->get('referer'),
            'Prémisse : référent envoyé seulement par le canal référent.',
        );

        return $response;
    }

    /**
     * Entrées réellement transportées par un payload de partage, lues sans
     * passer par la validation du codec (qui écarterait un champ inconnu et
     * masquerait une fuite).
     */
    private function entreesBrutesDuPayload(string $payload): array
    {
        $binaire = base64_decode(strtr($payload, '-_', '+/'), true);
        $this->assertIsString($binaire, 'Prémisse : payload base64url.');

        $json = gzinflate($binaire);
        $this->assertIsString($json, 'Prémisse : payload compressé.');

        $donnees = json_decode($json, true);
        $this->assertIsArray($donnees['i'] ?? null, 'Prémisse : entrées du payload.');

        return $donnees['i'];
    }

    // ------------------------------------------------------------------
    // 1. Validation : 422 JSON pour les clients JSON, 302 pour le HTML
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, string>, array<string, string>, ?string}>
     */
    public static function validationsJson(): array
    {
        return [
            'Accept JSON, salaire manquant' => [self::ENTETES_JSON, [], 'ui.validation.base_required'],
            'Accept JSON, salaire non numérique' => [self::ENTETES_JSON, ['salaire_base' => 'abc'], null],
            'XHR, salaire manquant' => [self::ENTETES_XHR, [], 'ui.validation.base_required'],
            'XHR, salaire négatif' => [self::ENTETES_XHR, ['salaire_base' => '-5'], 'ui.validation.base_positive'],
        ];
    }

    /**
     * Comportement historique de $request->validate() : un client qui attend
     * du JSON reçoit les erreurs en JSON, jamais une redirection HTML, même
     * quand le formulaire a posté sa cible de retour.
     */
    #[DataProvider('validationsJson')]
    public function test_une_erreur_de_validation_attendue_en_json_repond_422_sans_redirection(array $entetes, array $salaire, ?string $message): void
    {
        $donnees = self::SAISIE_INVALIDE + $salaire + [
            self::PARAMETRE_RETOUR => $this->resoudre('/calculateur?s={a}&a={a}'),
        ];

        $response = $this->withHeaders($entetes)->post('/calculateur/calculer', $donnees);

        $response->assertStatus(422);
        $response->assertHeaderMissing('Location');
        $response->assertJsonValidationErrors(['salaire_base']);
        $response->assertJsonMissingValidationErrors(['prime_rendement', 'retenues_imposees_ir', self::PARAMETRE_RETOUR]);

        $json = $response->json();
        $this->assertIsString($json['message'] ?? null, 'Message JSON présent.');
        $this->assertNotSame('', $json['message'], 'Message JSON non vide.');
        $this->assertIsString($json['errors']['salaire_base'][0] ?? null, 'Erreur du champ salaire_base.');
        $this->assertStringStartsWith($json['errors']['salaire_base'][0], $json['message'], 'Message JSON résumant la première erreur.');

        if ($message !== null) {
            $this->assertSame(__($message), $json['errors']['salaire_base'][0], 'Message du formulaire web conservé.');
        }

        $this->assertFalse(session()->hasOldInput(), 'Aucune saisie flashée pour un client JSON.');
        $response->assertSessionMissing('errors');
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>, ?string}>
     */
    public static function validationsHtml(): array
    {
        return [
            'formulaire sans en-tête Accept, salaire manquant' => [[], [], 'ui.validation.base_required'],
            'formulaire HTML, salaire manquant' => [self::ENTETES_HTML, [], 'ui.validation.base_required'],
            'formulaire HTML, salaire négatif' => [self::ENTETES_HTML, ['salaire_base' => '-5'], 'ui.validation.base_positive'],
        ];
    }

    /** Témoin : un formulaire HTML revient toujours à la cible de son onglet. */
    #[DataProvider('validationsHtml')]
    public function test_une_erreur_de_validation_html_revient_toujours_a_la_cible_de_l_onglet(array $entetes, array $salaire, string $message): void
    {
        $cible = $this->resoudre('/calculateur?s={a}&a={a}');
        $this->get('/documentation')->assertOk();

        $response = $this->withHeaders($entetes)->post('/calculateur/calculer', self::SAISIE_INVALIDE + $salaire + [
            self::PARAMETRE_RETOUR => $cible,
        ]);

        $response->assertStatus(302);
        $location = $this->assertRedirectionMemeOrigine($response, 'validation HTML');
        $this->assertSame($this->cheminEtRequete(url($cible)), $this->cheminEtRequete($location), 'Retour à la cible de l\'onglet.');
        $response->assertSessionHasErrors(['salaire_base' => __($message)]);
        $response->assertSessionHasInput('prime_rendement', '4321');
        $response->assertSessionHasInput('retenues_imposees_ir', '55');
    }

    // ------------------------------------------------------------------
    // 2. Langue, lien historique sans retour : même origine seulement
    // ------------------------------------------------------------------

    /**
     * Croise chaque cas avec les deux canaux de la page précédente.
     *
     * @param  array<string, list<string>>  $cas
     * @return array<string, list<string>>
     */
    private static function parCanal(array $cas): array
    {
        $croises = [];

        foreach ($cas as $nom => $arguments) {
            foreach (['referent' => 'référent', 'session' => 'session'] as $canal => $libelle) {
                $croises["{$nom}, {$libelle}"] = [$canal, ...$arguments];
            }
        }

        return $croises;
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function precedentesRefusees(): array
    {
        return self::parCanal([
            'autre port du même hôte' => ['http://{hote}:9999/calculateur?s={a}'],
            'information d\'utilisateur' => ['http://intrus@{hote}/calculateur?s={a}'],
            'utilisateur et mot de passe' => ['http://intrus:secret@{hote}/calculateur'],
            'schéma ftp du même hôte' => ['ftp://{hote}/calculateur?s={a}'],
            'schéma javascript du même hôte' => ['javascript://{hote}/%0Aalert(1)'],
            'page résultat POST' => ['{origine}/calculateur/calculer'],
            'route d\'API' => ['{origine}/api/v1/health'],
            'paramètres de l\'API' => ['{origine}/api/v1/parametres'],
            'sonde de santé' => ['{origine}/up'],
            'boucle de langue' => ['{origine}/lang/fr'],
            'boucle de langue imbriquée' => ['{origine}/lang/en?retour=%2Fcalculateur'],
            'route inconnue' => ['{origine}/inexistant'],
            'autre hôte' => ['https://evil.example/calculateur?s={a}'],
            'hôte suffixé' => ['http://{hote}.evil.example/calculateur'],
        ]);
    }

    #[DataProvider('precedentesRefusees')]
    public function test_le_lien_historique_de_langue_refuse_une_autre_origine_ou_une_route_exclue(string $canal, string $precedente): void
    {
        $response = $this->langueHistorique('en', $canal, $this->resoudre($precedente));

        $location = $this->assertRedirectionMemeOrigine($response, 'langue historique');
        $this->assertSame(['/', []], $this->cheminEtRequete($location), 'Repli sur l\'accueil.');
        $this->assertStringNotContainsString('9999', $location, 'Port étranger écarté.');
        $response->assertSessionHas('locale', 'en');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function precedentesRejouables(): array
    {
        return self::parCanal([
            'formulaire, paramètre étranger retiré' => ['{origine}/calculateur?s={a}&a={a}&x=1', '/calculateur?s={a}&a={a}'],
            'formulaire, profil' => ['{origine}/calculateur?profil=standard', '/calculateur?profil=standard'],
            'comparaison, paramètre étranger retiré' => ['{origine}/calculateur/comparer?a={a}&b={b}&s={a}', '/calculateur/comparer?a={a}&b={b}'],
            'gain 2027' => ['{origine}/calculateur/gain-2027?s={g}', '/calculateur/gain-2027?s={g}'],
            'documentation, payload retiré' => ['{origine}/documentation?s={a}', '/documentation'],
            'guide' => ['{origine}/calcul-salaire-net-maroc', '/calcul-salaire-net-maroc'],
            'accueil' => ['{origine}/', '/'],
        ]);
    }

    /**
     * Même origine : la page précédente est conservée, réduite à sa route
     * rejouable et à ses seuls paramètres utiles (s, a, b, profil selon la
     * route), sans jamais recopier un paramètre étranger.
     */
    #[DataProvider('precedentesRejouables')]
    public function test_le_lien_historique_de_langue_reconstruit_une_page_de_meme_origine(string $canal, string $precedente, string $attendue): void
    {
        $response = $this->langueHistorique('ar', $canal, $this->resoudre($precedente));

        $location = $this->assertRedirectionMemeOrigine($response, 'langue historique');
        $this->assertSame($this->cheminEtRequete(url($this->resoudre($attendue))), $this->cheminEtRequete($location));
        $response->assertSessionHas('locale', 'ar');

        $this->get($location)->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_le_lien_historique_de_langue_sans_page_precedente_revient_a_l_accueil(): void
    {
        $response = $this->langueHistorique('es', 'referent', null);

        $location = $this->assertRedirectionMemeOrigine($response, 'langue historique sans historique');
        $this->assertSame(['/', []], $this->cheminEtRequete($location));
        $response->assertSessionHas('locale', 'es');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function retoursExplicites(): array
    {
        return [
            'cible acceptée' => ['/documentation', '/documentation'],
            'cible refusée' => ['//evil.example/piege', '/'],
            'route exclue' => ['/calculateur/calculer', '/'],
        ];
    }

    /**
     * Un lien qui porte un retour explicite ne consulte ni le référent ni la
     * session, même quand ils désignent une page valide de même origine.
     */
    #[DataProvider('retoursExplicites')]
    public function test_un_retour_explicite_ignore_le_referent_et_la_session(string $retour, string $attendue): void
    {
        $autrePage = $this->resoudre('{origine}/calculateur?s={b}');

        $response = $this
            ->withSession(['_previous' => ['url' => $autrePage]])
            ->withHeader('Referer', $autrePage)
            ->get('/lang/en?'.http_build_query([self::PARAMETRE_RETOUR => $retour]));

        $location = $this->assertRedirectionMemeOrigine($response, 'langue explicite');
        $this->assertSame($this->cheminEtRequete(url($attendue)), $this->cheminEtRequete($location));
    }

    // ------------------------------------------------------------------
    // 3. Le champ de retour reste hors des payloads de partage
    // ------------------------------------------------------------------

    public function test_le_champ_de_retour_n_entre_jamais_dans_un_payload_de_partage(): void
    {
        $this->assertNotContains(self::PARAMETRE_RETOUR, PayrollValidation::webFields(), 'Le retour n\'est pas un champ de saisie.');

        $retour = $this->resoudre('/calculateur?s={a}');

        $resultat = $this->post('/calculateur/calculer', self::SAISIE_A + [self::PARAMETRE_RETOUR => $retour])->assertOk();
        $partage = $resultat->viewData('share_payload');
        $this->assertIsString($partage, 'Prémisse : résultat avec lien partagé.');
        $entrees = $this->entreesBrutesDuPayload($partage);
        $this->assertArrayHasKey('salaire_base', $entrees, 'Prémisse : lecture brute du payload effective.');
        $this->assertArrayNotHasKey(self::PARAMETRE_RETOUR, $entrees, 'Résultat : retour absent du payload.');

        $comparaison = $this->post('/calculateur/calculer', self::SAISIE_B + [
            'comparer_a' => $this->payload(self::SAISIE_A),
            self::PARAMETRE_RETOUR => $retour,
        ])->assertOk();

        foreach (['payload_a', 'payload_b'] as $cle) {
            $payload = $comparaison->viewData($cle);
            $this->assertIsString($payload, "Prémisse : comparaison avec {$cle}.");
            $this->assertArrayNotHasKey(self::PARAMETRE_RETOUR, $this->entreesBrutesDuPayload($payload), "Comparaison : retour absent de {$cle}.");
        }
    }
}
