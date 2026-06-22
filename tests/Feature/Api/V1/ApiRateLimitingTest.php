<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ApiRateLimitingTest extends TestCase
{
    public function test_rate_limit_allows_60_requests_and_blocks_61st(): void
    {
        $payload = ['salaire_base' => 5000, 'type_frais_pro' => 'commun'];

        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson('/api/v1/simuler/brut-vers-net', $payload);
            $response->assertOk();
        }

        $response = $this->postJson('/api/v1/simuler/brut-vers-net', $payload);
        $response->assertStatus(429)
            ->assertJson([
                'type' => 'about:blank',
                'title' => 'Too Many Requests',
                'status' => 429,
            ]);
    }
}
