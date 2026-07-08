<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 22 — regression: prior-sprint admin/public surfaces remain intact and the
 * new sales pipeline surface does not disturb existing behavior.
 */
class SalesPipelineRegressionRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_business_and_prior_admin_routes_still_registered(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        foreach ([
            'api/v1/auth/login',
            'api/v1/admin/tenants',
            'api/v1/admin/commercial-launch-go-no-go',
            'api/v1/admin/public-website-go-no-go',
            'api/v1/admin/sales-leads',
            'api/v1/admin/sales-pipeline/go-no-go',
        ] as $uri) {
            $this->assertContains($uri, $uris, "Missing route: {$uri}");
        }
    }

    public function test_prior_admin_and_public_website_routes_still_respond(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/tenants')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/commercial-package-summary')->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/public-website-lead-summary')->assertOk();
    }

    public function test_prior_and_current_gate_commands_remain_registered(): void
    {
        foreach ((array) config('sales_pipeline.required_commands', []) as $command) {
            $this->assertArrayHasKey($command, Artisan::all(), "Missing command: {$command}");
        }

        foreach ([
            'sales-pipeline:readiness',
            'sales-pipeline:lead-summary',
            'sales-pipeline:activity-summary',
            'sales-pipeline:go-no-go',
        ] as $command) {
            $this->assertArrayHasKey($command, Artisan::all(), "Missing command: {$command}");
        }
    }

    public function test_public_website_go_no_go_command_still_runs(): void
    {
        $this->artisan('public-website:go-no-go --json')->assertExitCode(1);
    }
}
