<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — the admin control panel must not weaken Sprint 10 enforcement.
 * Business APIs still require an allowed subscription AND an active device, and
 * platform admins do not gain tenant business access.
 */
class AdminNoRegressionBusinessApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->tenantUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    public function test_tenant_user_with_valid_subscription_and_device_can_access_business_api(): void
    {
        $this->actingAs($this->tenantUser, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();
    }

    public function test_expired_subscription_still_blocks_business_api(): void
    {
        $this->tenant->tenantSubscriptions()->delete();
        TenantSubscription::factory()->expired()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_plan_id' => $this->starterPlan()->id,
        ]);

        $this->actingAs($this->tenantUser, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(402)
            ->assertJsonPath('code', 'SUBSCRIPTION_INACTIVE');
    }

    public function test_missing_device_still_blocks_business_api(): void
    {
        $this->flushHeaders();

        $this->actingAs($this->tenantUser, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_NOT_REGISTERED');
    }

    public function test_platform_admin_does_not_gain_tenant_business_access(): void
    {
        // A platform admin carries no tenant; business routes require tenant
        // context and must not be served just because the user is an admin.
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(403);
    }
}
