<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Issue #170 : les en-têtes X-Forwarded-* ne sont crus que s'ils viennent d'un
 * proxy listé dans TRUSTED_PROXIES (config/trustedproxy.php), vide par défaut.
 */
class TrustedProxiesTest extends TestCase
{
    private const CLIENT = '198.51.100.9';

    private const SPOOFED = '203.0.113.77';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/proxy-probe', fn (Request $request) => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'url' => url('/calculateur'),
        ]);
    }

    public function test_no_proxy_is_trusted_by_default(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));
    }

    public function test_forwarded_headers_from_an_unlisted_peer_are_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
            ->withHeaders([
                'X-Forwarded-For' => self::SPOOFED,
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/__test/proxy-probe')
            ->assertOk()
            ->assertJson(['ip' => self::CLIENT, 'secure' => false]);
    }

    public function test_forwarded_headers_from_a_listed_proxy_are_honored(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8, 192.168.0.0/16']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders([
                'X-Forwarded-For' => self::CLIENT,
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/__test/proxy-probe')
            ->assertOk()
            ->assertJson(['ip' => self::CLIENT, 'secure' => true])
            ->assertJsonPath('url', fn (string $url) => str_starts_with($url, 'https://'));
    }

    public function test_a_listed_proxy_does_not_let_the_client_choose_its_address(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);

        // Le client envoie un X-Forwarded-For forgé, le proxy y ajoute l'adresse réelle.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-For' => self::SPOOFED.', '.self::CLIENT])
            ->get('/__test/proxy-probe')
            ->assertJson(['ip' => self::CLIENT]);
    }

    public function test_api_rate_limit_cannot_be_bypassed_with_x_forwarded_for(): void
    {
        $payload = ['salaire_base' => 5000, 'type_frais_pro' => 'commun'];
        $server = ['REMOTE_ADDR' => self::CLIENT];

        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables($server)
                ->postJson('/api/v1/simuler/brut-vers-net', $payload)
                ->assertOk();
        }

        foreach ([self::SPOOFED, '192.0.2.1'] as $forged) {
            $this->withServerVariables($server)
                ->withHeaders(['X-Forwarded-For' => $forged])
                ->postJson('/api/v1/simuler/brut-vers-net', $payload)
                ->assertStatus(429);
        }
    }

    /**
     * Forme de l'image de release : Nginx a déjà résolu l'adresse réelle et
     * transmet HTTPS=on à PHP-FPM quand l'amont de confiance était en HTTPS.
     * Le client de test dérive HTTPS du schéma de l'URI : une URI https://
     * reproduit ce que PHP-FPM reçoit.
     */
    public function test_https_passed_by_the_release_nginx_yields_secure_urls_and_cookies(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
            ->get('https://localhost/__test/proxy-probe')
            ->assertJson(['ip' => self::CLIENT, 'secure' => true])
            ->assertJsonPath('url', fn (string $url) => str_starts_with($url, 'https://'));

        $cookie = $this->withServerVariables(['REMOTE_ADDR' => self::CLIENT])
            ->get('https://localhost/')
            ->assertOk()
            ->getCookie(config('session.cookie'), false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }
}
