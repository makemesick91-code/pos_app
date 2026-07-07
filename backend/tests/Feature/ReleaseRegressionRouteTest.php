<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 13 — release regression contract: every business/admin/onboarding API
 * surface from Sprints 1–12 must remain registered. A dropped route is a
 * release-blocking regression.
 */
class ReleaseRegressionRouteTest extends TestCase
{
    /**
     * @return array<int,string>
     */
    private function registeredUris(): array
    {
        return collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();
    }

    private function assertRouteRegistered(string $uri, array $registered): void
    {
        $found = false;
        foreach ($registered as $registeredUri) {
            if ($registeredUri === $uri || str_starts_with($registeredUri, $uri.'/')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Expected a registered route matching '{$uri}'.");
    }

    public function test_all_foundation_routes_remain_registered(): void
    {
        $registered = $this->registeredUris();

        $expected = [
            // health
            'api/health',
            // auth
            'api/v1/auth/login',
            // tenant context
            'api/v1/tenant-context',
            // sync products/categories
            'api/v1/sync/products',
            'api/v1/sync/categories',
            // sales
            'api/v1/sales',
            // payments / qris / webhooks
            'api/v1/payments',
            'api/v1/webhooks/payments',
            // receipt (nested under sales/{sale}/receipt)
            'api/v1/sales/{sale}/receipt',
            // inventory
            'api/v1/inventory',
            // reports
            'api/v1/reports/daily-sales',
            // closings
            'api/v1/closings/daily',
            // subscription / devices
            'api/v1/subscription/status',
            'api/v1/devices',
            // admin
            'api/v1/admin/tenants',
            'api/v1/admin/subscription-plans',
            'api/v1/admin/audit-logs',
            // onboarding
            'api/v1/admin/tenant-onboarding',
        ];

        foreach ($expected as $uri) {
            $this->assertRouteRegistered($uri, $registered);
        }
    }
}
