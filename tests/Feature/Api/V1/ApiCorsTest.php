<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ApiCorsTest extends TestCase
{
    public function test_options_preflight_returns_cors_headers(): void
    {
        $response = $this->options('/api/v1/simuler/brut-vers-net', [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS-CONTROL-REQUEST-METHOD' => 'POST',
            'HTTP_ACCESS-CONTROL-REQUEST-HEADERS' => 'Content-Type',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_post_response_includes_cors_headers(): void
    {
        $response = $this->postJson('/api/v1/simuler/brut-vers-net', [
            'salaire_base' => 5000,
            'type_frais_pro' => 'commun',
        ], [
            'HTTP_ORIGIN' => 'https://example.com',
        ]);

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_get_response_includes_cors_headers(): void
    {
        $response = $this->getJson('/api/v1/health', [
            'HTTP_ORIGIN' => 'https://example.com',
        ]);

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
