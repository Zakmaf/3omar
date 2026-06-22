<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthApiTest extends TestCase
{
    public function test_get_returns_200_with_status_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'status',
                'version',
                'timestamp',
            ]);
    }

    public function test_version_matches_config(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'version' => config('app.version'),
            ]);
    }

    public function test_timestamp_is_iso8601(): void
    {
        $response = $this->getJson('/api/v1/health');

        $data = $response->json();
        $this->assertNotNull($data['timestamp']);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['timestamp']));
    }
}
