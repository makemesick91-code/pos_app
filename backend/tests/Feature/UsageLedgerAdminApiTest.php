<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sprint 28 — platform-admin read-only anomaly/repair visibility; non-admins are
 * blocked; output is redacted; and there is NO runtime mutation route for the
 * usage ledger or repair records (ULR-R009, ULR-R012).
 */
class UsageLedgerAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function suspiciousDuplicate(Tenant $tenant): void
    {
        foreach (['k1', 'k2'] as $key) {
            TenantUsageEvent::query()->create([
                'tenant_id' => $tenant->id,
                'event_key' => TenantUsageEvent::EVENT_REPORT_EXPORTED,
                'event_category' => TenantUsageEvent::CATEGORY_REPORT_EXPORT,
                'meter_key' => TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY,
                'quantity' => 1,
                'occurred_at' => Carbon::create(2026, 7, 15, 10),
                'period_key' => '2026-07',
                'idempotency_key' => $key,
                'source' => 'api',
                'request_fingerprint' => 'dupe-fp',
                'metadata' => ['api_token' => 'sk_live_HIDE_ME'],
            ]);
        }
    }

    public function test_platform_admin_can_view_global_anomaly_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->suspiciousDuplicate($tenant);
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/usage-ledger/anomalies')
            ->assertOk()
            ->assertJsonPath('data.total', fn ($t) => $t >= 1);

        // No raw secret value leaks in the response body.
        $this->assertStringNotContainsString('sk_live_HIDE_ME', $response->getContent());
    }

    public function test_platform_admin_can_view_tenant_scoped_anomaly_summary(): void
    {
        $tenant = Tenant::factory()->create();
        $this->suspiciousDuplicate($tenant);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/usage-ledger/anomalies")
            ->assertOk()
            ->assertJsonPath('data.critical', fn ($c) => $c >= 1);
    }

    public function test_platform_admin_can_view_repair_summary(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/usage-ledger/repair-summary')
            ->assertOk()
            ->assertJsonPath('data.total_repairs', 0);
    }

    public function test_non_platform_admin_cannot_view_anomaly_summary(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/usage-ledger/anomalies')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_anomaly_summary(): void
    {
        $this->getJson('/api/v1/admin/usage-ledger/anomalies')->assertUnauthorized();
    }

    public function test_there_is_no_runtime_mutation_route_for_usage_ledger(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_contains($uri, 'usage-events') && ! str_contains($uri, 'usage-ledger')) {
                continue;
            }
            $mutating = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
            $this->assertEmpty(
                $mutating,
                "Unexpected mutating route on the usage ledger: {$uri} [".implode(',', $route->methods()).']',
            );
        }
    }
}
