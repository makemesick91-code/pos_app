<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 29 — platform-admin read-only export governance visibility; normal
 * tenant users cannot see it, responses describe route governance (not tenant
 * usage), and there is no runtime metering-bypass or ledger-mutation route
 * (EGC-R011, EGC-R012).
 */
class ExportGovernanceAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_export_governance_routes(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/export-governance/routes')
            ->assertOk()
            ->assertJsonPath('data.registered_routes.0.signature', 'GET api/v1/reports/daily-sales/export.csv');
    }

    public function test_platform_admin_can_view_coverage_and_metering_summary(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/export-governance/coverage-summary')
            ->assertOk()
            ->assertJsonPath('data.meter_key', 'reports.exports.monthly')
            ->assertJsonPath('data.meterable', true)
            ->assertJsonPath('data.totals.metered_routes', 1)
            ->assertJsonPath('data.totals.gaps', 0);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/export-governance/metering-summary')
            ->assertOk()
            ->assertJsonPath('data.meter_key', 'reports.exports.monthly')
            ->assertJsonPath('data.event_key', 'report.exported');
    }

    public function test_non_platform_admin_cannot_view_export_governance(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/export-governance/routes')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/export-governance/coverage-summary')
            ->assertForbidden();
    }

    public function test_no_export_metering_bypass_or_ledger_mutation_route_exists(): void
    {
        $routes = app('router')->getRoutes();
        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $isMutation = (bool) array_intersect($methods, ['POST', 'PUT', 'PATCH', 'DELETE']);

            if ($isMutation && str_contains($uri, 'export-governance')) {
                $this->fail("Unexpected mutation route on export-governance: {$uri}");
            }
            if ($isMutation && (str_contains($uri, 'usage-events') || str_contains($uri, 'usage-ledger'))) {
                $this->fail("Unexpected usage ledger mutation route: {$uri}");
            }
        }

        $this->assertTrue(true);
    }
}
