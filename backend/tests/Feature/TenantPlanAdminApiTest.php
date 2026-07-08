<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 26 — platform-admin tenant plan / entitlement governance (TPE-R006,
 * TPE-R007). Plan catalogue and assignment/override mutations are platform-admin
 * only, reason-aware, and audit-logged.
 */
class TenantPlanAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_list_plans(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/tenant-plans')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'starter');
    }

    public function test_platform_admin_can_create_plan(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/v1/admin/tenant-plans', [
                'key' => 'custom_pilot',
                'name' => 'Custom Pilot',
                'description' => 'Pilot plan',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.key', 'custom_pilot');

        $this->assertDatabaseHas('tenant_plans', ['key' => 'custom_pilot']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'tenant_plan.create']);
    }

    public function test_non_platform_admin_cannot_create_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/admin/tenant-plans', ['key' => 'x', 'name' => 'X'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }

    public function test_platform_admin_can_assign_plan_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/plan", [
                'plan_key' => 'growth',
                'source' => 'platform_admin',
                'reason' => 'Upgraded to growth.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.plan.plan_key', 'growth');

        $this->assertDatabaseHas('tenant_plan_assignments', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'tenant.plan_assign',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_assign_plan_rejects_unknown_plan_key(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/plan", ['plan_key' => 'does_not_exist'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_key');
    }

    public function test_non_admin_cannot_assign_plan(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/plan", ['plan_key' => 'growth'])
            ->assertStatus(403);

        $this->assertDatabaseMissing('tenant_plan_assignments', ['tenant_id' => $tenant->id, 'source' => 'platform_admin']);
    }

    public function test_platform_admin_can_view_tenant_entitlements(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/entitlements")
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'enterprise')
            ->assertJsonStructure(['data' => ['entitlements' => ['pos.sales']]]);
    }

    public function test_platform_admin_can_create_entitlement_override(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/entitlement-overrides", [
                'entitlement_key' => 'reports.advanced',
                'enabled' => false,
                'reason' => 'Downgrade advanced reports.',
                'reason_category' => 'SUPPORT',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('tenant_entitlement_overrides', [
            'tenant_id' => $tenant->id,
            'entitlement_key' => 'reports.advanced',
            'enabled' => false,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'tenant.entitlement_override',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_entitlement_override_requires_a_reason(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/entitlement-overrides", [
                'entitlement_key' => 'pos.refunds',
                'enabled' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_non_admin_cannot_create_entitlement_override(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/entitlement-overrides", [
                'entitlement_key' => 'pos.refunds',
                'enabled' => true,
                'reason' => 'self grant',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('tenant_entitlement_overrides', 0);
    }

    public function test_override_reason_secrets_are_redacted(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/entitlement-overrides", [
                'entitlement_key' => 'pos.refunds',
                'enabled' => true,
                'reason' => 'grant with token: abc123secretvalue',
                'reason_category' => 'SUPPORT',
            ])->assertStatus(201);

        $override = \App\Models\TenantEntitlementOverride::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertStringNotContainsString('abc123secretvalue', (string) $override->reason);
        $this->assertStringContainsString('[REDACTED]', (string) $override->reason);
    }

    public function test_platform_admin_can_view_usage_limits(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/usage-limits")
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'enterprise')
            ->assertJsonStructure(['data' => ['limits' => ['products.max']]]);
    }

    public function test_platform_admin_can_view_governance_summary(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/tenant-plan-governance/summary')
            ->assertOk()
            ->assertJsonPath('data.plans', 4);
    }
}
