<?php

namespace Tests\Feature;

use App\Services\GuideService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Toute ressource tierce versionnée (script ou feuille de style) doit être
 * chargée avec une empreinte SRI et crossorigin="anonymous" (#43, #186).
 */
class SubresourceIntegrityTest extends TestCase
{
    /**
     * Hôtes dont le contenu n'est pas figé, donc sans SRI possible :
     * la CSS de Google Fonts est générée selon le navigateur, le script
     * AdSense n'est pas versionné (hors périmètre de #186).
     */
    private const HOSTS_WITHOUT_SRI = [
        'fonts.googleapis.com',
        'pagead2.googlesyndication.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_public_pages_load_third_party_resources_with_integrity(): void
    {
        $pages = [
            '/' => $this->get('/')->assertOk()->getContent(),
            '/calculateur' => $this->get('/calculateur')->assertOk()->getContent(),
            '/documentation' => $this->get('/documentation')->assertOk()->getContent(),
            '/fiabilite' => $this->get('/fiabilite')->assertOk()->getContent(),
            '/api-documentation' => $this->get('/api-documentation')->assertOk()->getContent(),
            'page résultat' => $this->post('/calculateur/calculer', [
                'salaire_base' => 10000,
                'type_frais_pro' => 'commun',
            ])->assertOk()->getContent(),
        ];

        $slug = array_key_first(GuideService::GUIDES);
        $pages['/'.$slug] = $this->get('/'.$slug)->assertOk()->getContent();

        $checked = [];

        foreach ($pages as $page => $html) {
            foreach ($this->thirdPartyResourceTags($html) as $url => $tag) {
                $this->assertMatchesRegularExpression(
                    '/\sintegrity="sha(256|384|512)-[A-Za-z0-9+\/=]+"/',
                    $tag,
                    "{$page} : {$url} est chargé sans attribut integrity.",
                );
                $this->assertStringContainsString(
                    'crossorigin="anonymous"',
                    $tag,
                    "{$page} : {$url} est chargé sans crossorigin=\"anonymous\".",
                );
                $checked[$url] = true;
            }
        }

        // Garde-fou : le test doit réellement voir les ressources jsDelivr.
        $this->assertArrayHasKey('https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js', $checked);
        $this->assertArrayHasKey('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', $checked);
        $this->assertArrayHasKey('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', $checked);
        $this->assertArrayHasKey('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', $checked);
    }

    /**
     * Balises <script src="https://..."> et <link rel="stylesheet" href="https://...">
     * dont l'hôte n'est pas dans la liste des exceptions, indexées par URL.
     *
     * @return array<string, string>
     */
    private function thirdPartyResourceTags(string $html): array
    {
        preg_match_all('/<(script|link)\b[^>]*>/i', $html, $matches);

        $tags = [];

        foreach ($matches[0] as $tag) {
            $isScript = str_starts_with(strtolower($tag), '<script');

            if (! $isScript && ! preg_match('/\srel="stylesheet"/i', $tag)) {
                continue;
            }

            $attribute = $isScript ? 'src' : 'href';

            if (! preg_match('/\s'.$attribute.'="(https?:\/\/[^"]+)"/i', $tag, $url)) {
                continue;
            }

            $host = parse_url(html_entity_decode($url[1]), PHP_URL_HOST);

            if (in_array($host, self::HOSTS_WITHOUT_SRI, true)) {
                continue;
            }

            $tags[$url[1]] = $tag;
        }

        return $tags;
    }
}
