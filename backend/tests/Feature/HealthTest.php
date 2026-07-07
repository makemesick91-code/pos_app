<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * The health endpoint must respond without any database dependency
     * so it can be validated in CI and on fresh environments (Sprint 0).
     */
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'app' => 'Aish POS Lite API',
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
                'sprint' => 'Sprint 5',
            ]);
    }
}
