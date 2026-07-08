<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPlan\TenantEntitlementOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 26 — the tenant.entitled guard blocks an operational route server-side
 * when the tenant plan (with active overrides applied) does not grant the feature
 * (TPE-R002, TPE-R008). Entitled tenants pass.
 */
class TenantPlanEntitlementEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-PLAN']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'PL1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function disable(string $feature): void
    {
        $admin = User::factory()->platformAdmin()->create();
        app(TenantEntitlementOverrideService::class)->set(
            tenant: $this->tenant,
            actor: $admin,
            entitlementKey: $feature,
            enabled: false,
            reason: 'Disable for enforcement test.',
            reasonCategory: 'SUPPORT',
        );
    }

    private function enable(string $feature): void
    {
        $admin = User::factory()->platformAdmin()->create();
        app(TenantEntitlementOverrideService::class)->set(
            tenant: $this->tenant,
            actor: $admin,
            entitlementKey: $feature,
            enabled: true,
            reason: 'Enable for enforcement test.',
            reasonCategory: 'PROMOTION',
        );
    }

    public function test_entitled_tenant_can_access_reports(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk();
    }

    public function test_unentitled_tenant_is_blocked_with_feature_not_entitled(): void
    {
        $this->disable('reports.basic');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FEATURE_NOT_ENTITLED')
            ->assertJsonPath('feature', 'reports.basic');
    }

    public function test_disabling_pos_sales_blocks_the_sales_surface(): void
    {
        $this->disable('pos.sales');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/sales')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FEATURE_NOT_ENTITLED');
    }

    public function test_override_can_re_enable_a_feature(): void
    {
        $this->disable('reports.basic');
        $this->enable('reports.basic');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/daily-sales')
            ->assertOk();
    }
}
