<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_public_pages_have_security_headers(): void
    {
        foreach (['/', '/calculateur', '/documentation'] as $path) {
            $response = $this->get($path)->assertOk();

            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-Frame-Options', 'DENY');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
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
