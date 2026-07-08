<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Models\User;
use App\Services\UsageEventLedger\UsageEventLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 27 — platform-admin read-only usage event inspection; normal tenant
 * users cannot see usage events, summaries are redacted, and there is no runtime
 * mutation route for the append-only ledger (UEL-R002, UEL-R003, UEL-R013).
 */
class UsageEventAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedEvent(Tenant $tenant, string $key = 'a-1'): void
    {
        app(UsageEventLedgerService::class)->append(
            tenant: $tenant,
            eventKey: TenantUsageEvent::EVENT_REPORT_EXPORTED,
            eventCategory: TenantUsageEvent::CATEGORY_REPORT_EXPORT,
            meterKey: TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY,
            idempotencyKey: $key,
            metadata: ['report_type' => 'daily-sales', 'api_token' => 'sk_live_should_be_hidden'],
        );
    }

    public function test_platform_admin_can_view_tenant_usage_events(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedEvent($tenant);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/usage-events")
            ->assertOk()
            ->assertJsonPath('data.0.event_key', 'report.exported')
            ->assertJsonPath('data.0.metadata.api_token', '[REDACTED]');
    }

    public function test_platform_admin_can_view_tenant_and_global_summaries(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedEvent($tenant);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/usage-events/summary")
            ->assertOk()
            ->assertJsonPath('data.meters.0.meter_key', 'reports.exports.monthly');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/usage-event-ledger/summary')
            ->assertOk()
            ->assertJsonPath('data.meters.0.event_key', 'report.exported');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/report-export-metering/summary')
            ->assertOk()
            ->assertJsonPath('data.meter_key', 'reports.exports.monthly')
            ->assertJsonPath('data.meterable', true);
    }

    public function test_non_platform_admin_cannot_view_usage_events(): void
    {
        $tenant = Tenant::factory()->create();
        $this->seedEvent($tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/usage-events")
            ->assertForbidden();
    }

    public function test_no_runtime_mutation_route_for_usage_events(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        // The usage-events endpoint is GET-only; the ledger is append-only, so a
        // mutating verb on it is method-not-allowed and no {id} mutation route
        // exists at all (UEL-R002, UEL-R013).
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/tenants/{$tenant->id}/usage-events")
            ->assertStatus(405);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/tenants/{$tenant->id}/usage-events")
            ->assertStatus(405);

        // No per-event mutation route is registered at all.
        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/tenants/{$tenant->id}/usage-events/1")
            ->assertStatus(404);
    }
}
