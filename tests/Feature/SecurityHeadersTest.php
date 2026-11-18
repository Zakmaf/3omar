<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_pages_have_security_headers(): void
    {
        // Referrer-Policy différenciée (#283) : no-referrer sur le calculateur.
        $politiques = [
            '/' => SecurityHeaders::REFERRER_POLICY,
            '/calculateur' => SecurityHeaders::REFERRER_POLICY_CALCULATEUR,
            '/documentation' => SecurityHeaders::REFERRER_POLICY,
        ];

        foreach ($politiques as $path => $politique) {
            $response = $this->get($path)->assertOk();

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', $politique);
            $response->assertHeader('Content-Security-Policy');
        }
    }

    public function test_csp_allows_required_external_sources(): void
    {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    public function test_csp_blocks_frames_when_ads_disabled(): void
    {
        config()->set('ads.enabled', false);

        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-src 'none'", $csp);
    }

    public function test_docker_php_ini_hides_php_version(): void
    {
        $ini = parse_ini_file(base_path('docker/php/php.ini'), true, INI_SCANNER_RAW);

        $this->assertIsArray($ini);
        $this->assertSame('Off', $ini['PHP']['expose_php'] ?? null);
    }

    /**
     * Dans l'image de production, Nginx est la seule source de ces trois
     * en-têtes (#185) : ses valeurs doivent rester celles du middleware.
     */
    public function test_release_nginx_sends_the_middleware_header_values(): void
    {
        $conf = file_get_contents(base_path('docker/release/nginx.conf'));
        $headers = $this->get('/')->assertOk()->headers;

        // Referrer-Policy est différenciée par route (#283) : voir
        // test_release_nginx_differentiates_the_referrer_policy_like_the_middleware.
        foreach (['X-Content-Type-Options', 'X-Frame-Options'] as $name) {
            preg_match_all('/^\s*add_header\s+'.preg_quote($name, '/').'\s+"([^"]+)"\s+always;/mi', $conf, $values);

            $this->assertNotEmpty($values[1], "{$name} absent de docker/release/nginx.conf.");
            $this->assertSame(
                [$headers->get($name)],
                array_values(array_unique($values[1])),
                "{$name} : Nginx et le middleware SecurityHeaders divergent.",
            );
            $this->assertMatchesRegularExpression(
                '/^\s*fastcgi_hide_header\s+'.preg_quote($name, '/').';/mi',
                $conf,
                "{$name} : la valeur PHP n'est pas masquée, l'en-tête serait envoyé deux fois.",
            );
        }
    }

    public function test_the_middleware_differentiates_the_referrer_policy(): void
    {
        $this->assertSame('strict-origin-when-cross-origin', SecurityHeaders::REFERRER_POLICY);
        $this->assertSame('no-referrer', SecurityHeaders::REFERRER_POLICY_CALCULATEUR);

        foreach (['/', '/documentation'] as $path) {
            $this->assertSame([SecurityHeaders::REFERRER_POLICY], $this->get($path)->assertOk()->headers->all('referrer-policy'), $path);
        }
        foreach (['/calculateur', '/calculateur?s=invalide'] as $path) {
            $this->assertSame([SecurityHeaders::REFERRER_POLICY_CALCULATEUR], $this->get($path)->assertOk()->headers->all('referrer-policy'), $path);
        }
        $this->assertSame(
            [SecurityHeaders::REFERRER_POLICY_CALCULATEUR],
            $this->get('/calculateur/comparer?a=x&b=y')->assertRedirect()->headers->all('referrer-policy'),
            'Redirection du calculateur.',
        );
    }

    /**
     * Dans l'image de production, Nginx pose seul Referrer-Policy (#185), avec
     * la même différenciation que le middleware (#283) : une map sur
     * $request_uri (URI d'origine, non réécrite par try_files) donne
     * no-referrer aux chemins /calculateur, sinon la valeur PHP reprise de
     * l'amont, sinon la valeur éditoriale. L'en-tête PHP reste masqué.
     */
    public function test_release_nginx_differentiates_the_referrer_policy_like_the_middleware(): void
    {
        $conf = file_get_contents(base_path('docker/release/nginx.conf'));

        // Chaque add_header Referrer-Policy (bloc server et statiques) utilise la map, aucune valeur figée.
        preg_match_all('/^\s*add_header\s+Referrer-Policy\s+(\S+)\s+always;/mi', $conf, $valeurs);
        $this->assertCount(2, $valeurs[1], 'add_header Referrer-Policy dans le bloc server et le bloc des statiques.');
        $this->assertSame(['$referrer_policy'], array_values(array_unique($valeurs[1])), 'Valeur issue de la map, jamais figée.');
        $this->assertSame(2, preg_match_all('/^\s*add_header\s+Referrer-Policy\b/mi', $conf), 'Aucun autre add_header Referrer-Policy.');
        $this->assertMatchesRegularExpression('/^\s*fastcgi_hide_header\s+Referrer-Policy;/mi', $conf, 'En-tête PHP masqué : un seul envoyé.');

        // Map de l'URI d'origine.
        $this->assertSame(1, preg_match('/map\s+\$request_uri\s+\$referrer_policy\s*\{(.*?)\}/s', $conf, $mapUri), 'Map sur $request_uri.');
        $this->assertSame(1, preg_match('/^\s*"~([^"]+)"\s+"([^"]+)";/m', $mapUri[1], $regle), 'Règle regex du calculateur.');
        [, $regex, $politique] = $regle;
        $this->assertSame(SecurityHeaders::REFERRER_POLICY_CALCULATEUR, $politique);
        $this->assertMatchesRegularExpression('/^\s*default\s+\$referrer_policy_app;/m', $mapUri[1]);

        // Map de repli : valeur PHP, sinon valeur éditoriale du middleware.
        $this->assertSame(1, preg_match('/map\s+\$upstream_http_referrer_policy\s+\$referrer_policy_app\s*\{(.*?)\}/s', $conf, $mapApp));
        $this->assertMatchesRegularExpression('/^\s*""\s+"'.preg_quote(SecurityHeaders::REFERRER_POLICY, '/').'";/m', $mapApp[1]);
        $this->assertMatchesRegularExpression('/^\s*default\s+\$upstream_http_referrer_policy;/m', $mapApp[1]);

        // Preuve de la regex (PCRE, comme Nginx) sur les URI d'origine.
        $motif = '#'.str_replace('#', '\#', $regex).'#';
        $calculateur = [
            '/calculateur', '/calculateur?s=abc', '/calculateur/', '/calculateur/calculer',
            '/calculateur/comparer?a=x&b=y', '/calculateur/gain-2027?s=abc', '//calculateur?s=abc',
        ];
        foreach (Route::getRoutes() as $route) {
            if (is_string($route->getName()) && str_starts_with($route->getName(), 'calculator.')) {
                $calculateur[] = '/'.ltrim($route->uri(), '/').'?s=abc';
            }
        }
        foreach ($calculateur as $uri) {
            $this->assertSame(1, preg_match($motif, $uri), "{$uri} : no-referrer attendu.");
        }
        foreach (['/', '/?s=abc', '/documentation', '/fiabilite', '/calculateurs', '/calcul-salaire-net-maroc', '/api/v1/health', '/img/app_icon_32.png', '/lang/fr'] as $uri) {
            $this->assertSame(0, preg_match($motif, $uri), "{$uri} : politique éditoriale attendue.");
        }
    }

    public function test_hsts_not_set_on_http(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_set_on_https(): void
    {
        $response = $this->get('https://localhost/')->assertOk();

        $this->assertEquals(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security')
        );
    }
}
