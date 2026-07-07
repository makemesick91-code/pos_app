<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 19 — the operations layer must not break the cumulative route/command
 * contract from Sprint 0–18, and the new admin operations routes must be wired.
 */
class ProductionOperationsRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_business_and_admin_routes_remain_registered(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        $expected = [
            'api/health',
            'api/v1/auth/login',
            'api/v1/tenant-context',
            'api/v1/sync/products',
            'api/v1/sales',
            'api/v1/webhooks/payments/{provider}',
            'api/v1/inventory/movements',
            'api/v1/reports/daily-sales',
            'api/v1/closings/daily',
            'api/v1/subscription/status',
            'api/v1/devices',
            'api/v1/admin/pilot-defects',
            'api/v1/admin/pilot-closures',
            'api/v1/admin/production-handovers',
            'api/v1/admin/production-handover-go-no-go',
            'api/v1/admin/production-operation-runs',
            'api/v1/admin/production-incidents',
            'api/v1/admin/production-maintenance-windows',
            'api/v1/admin/production-ops-health',
            'api/v1/admin/production-incident-summary',
            'api/v1/admin/production-post-handover-go-no-go',
        ];

        foreach ($expected as $uri) {
            $this->assertContains($uri, $uris, "Missing route: {$uri}");
        }
    }

    public function test_cumulative_release_pilot_handover_operations_commands_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ((array) config('production_operations.required_commands', []) as $command) {
            $this->assertContains($command, $registered, "Missing command: {$command}");
        }
    }
}
