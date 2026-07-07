<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 16 — pilot monitoring regression contract. Confirms the Sprint 0–15 API
 * surface and the release + pilot RC/UAT + deployment/field + monitoring/hypercare
 * Artisan commands remain registered, so the pilot monitoring foundation does not
 * silently break existing tenant/business behavior.
 */
class PilotMonitoringRegressionRouteTest extends TestCase
{
    /**
     * @return array<int,string>
     */
    private function registeredUris(): array
    {
        return collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();
    }

    private function assertRouteRegistered(string $uri): void
    {
        $target = ltrim($uri, '/');
        foreach ($this->registeredUris() as $registered) {
            if ($registered === $target
                || str_starts_with($registered, $target.'/')
                || str_starts_with($registered, $target.'/{')) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail("Expected route '{$uri}' to be registered.");
    }

    public function test_required_routes_are_registered(): void
    {
        $required = [
            'api/health',
            'api/v1/auth/login',
            'api/v1/tenant-context',
            'api/v1/sync/products',
            'api/v1/sync/categories',
            'api/v1/sales',
            'api/v1/sales/{sale}/payments/cash',
            'api/v1/sales/{sale}/payments/qris',
            'api/v1/payments/{payment}/status',
            'api/v1/webhooks/payments/{provider}',
            'api/v1/sales/{sale}/receipt',
            'api/v1/inventory/current-stock',
            'api/v1/reports/daily-sales',
            'api/v1/closings/daily',
            'api/v1/subscription/status',
            'api/v1/devices',
            'api/v1/admin/tenants',
            'api/v1/admin/tenant-onboarding',
            'api/v1/admin/tenants/{tenant}/onboarding-status',
            'api/v1/admin/tenants/{tenant}/demo-data',
        ];

        foreach ($required as $uri) {
            $this->assertRouteRegistered($uri);
        }
    }

    public function test_release_pilot_and_monitoring_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        foreach ([
            'production:readiness-check',
            'release:go-no-go',
            'pilot:rc-check',
            'pilot:uat-summary',
            'pilot:deployment-check',
            'pilot:field-trial-summary',
            'pilot:daily-monitoring-check',
            'pilot:health-summary',
            'hypercare:issue-triage',
        ] as $command) {
            $this->assertContains($command, $commands, "Command '{$command}' must be registered.");
        }
    }
}
