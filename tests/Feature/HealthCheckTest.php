<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_ok_json_without_authentication(): void
    {
        $response = $this->getJson('/health');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
            ])
            ->assertJson([
                'status' => 'ok',
            ]);

        $this->assertNotEmpty($response->json('timestamp'));
    }
}
