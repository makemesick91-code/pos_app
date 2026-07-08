<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 25 — the tenant.lifecycle runtime guard blocks operational (POS) APIs
 * for a manually suspended tenant, server-side (TLS-R003). The enforcement
 * allowlist (auth/status/device) stays reachable while suspended (TLS-R007).
 */
class TenantLifecycleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-LC']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'LC1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function suspend(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend(
            tenant: $this->tenant,
            actor: $admin,
            reason: 'Manual suspension for enforcement test.',
            reasonCategory: 'PAYMENT_OVERDUE',
        );
    }

    public function test_active_tenant_can_access_operational_api(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();
    }

    public function test_suspended_tenant_is_blocked_from_operational_api(): void
    {
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(423)
            ->assertJsonPath('code', 'TENANT_SUSPENDED')
            ->assertJsonPath('tenant_status', 'suspended');
    }

    public function test_suspended_tenant_blocked_on_sales_write(): void
    {
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/sales', [])
            ->assertStatus(423)
            ->assertJsonPath('code', 'TENANT_SUSPENDED');
    }

    public function test_allowlisted_subscription_status_reachable_while_suspended(): void
    {
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk();
    }

    public function test_allowlisted_device_list_reachable_while_suspended(): void
    {
        $this->suspend();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/devices')
            ->assertOk();
    }

    public function test_lifting_restores_operational_access(): void
    {
        $this->suspend();

        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->lift(
            tenant: $this->tenant,
            actor: $admin,
            reason: 'Resolved.',
        );

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();
    }
}
