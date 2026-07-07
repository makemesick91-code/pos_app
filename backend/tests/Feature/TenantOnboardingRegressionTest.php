<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — onboarding must not weaken earlier sprints. Sprint 11 admin APIs
 * stay platform-admin only; Sprint 10 subscription/device enforcement applies to
 * a freshly onboarded tenant; and existing business APIs keep working.
 */
class TenantOnboardingRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
        $this->plan = SubscriptionPlan::factory()->starter()->create();
    }

    private function onboard(): Tenant
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', [
                'onboarding_reference' => 'reg-001',
                'tenant_name' => 'Regression Tenant',
                'tenant_code' => 'reg-tenant',
                'store_name' => 'Regression Store',
                'owner_name' => 'Regression Owner',
                'owner_email' => 'reg.owner@example.test',
                'owner_password' => 'temporary-password',
                'subscription_plan_id' => $this->plan->id,
                'subscription_status' => 'TRIAL',
                'demo_data_enabled' => true,
            ])
            ->assertCreated();

        return Tenant::query()->where('code', 'reg-tenant')->firstOrFail();
    }

    public function test_sprint11_admin_apis_remain_platform_admin_only(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_TENANT_OWNER]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(403);
    }

    public function test_onboarded_owner_can_login_and_read_subscription(): void
    {
        $this->onboard();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'reg.owner@example.test',
            'password' => 'temporary-password',
        ])->assertOk();

        $this->assertNotEmpty($login->json('token'));

        $owner = User::query()->where('email', 'reg.owner@example.test')->firstOrFail();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', true);
    }

    public function test_onboarded_tenant_business_api_enforces_device_then_works(): void
    {
        $this->onboard();
        $owner = User::query()->where('email', 'reg.owner@example.test')->firstOrFail();

        // No device registered for the header UUID yet → blocked by Sprint 10 gate.
        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/sync/products')
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_NOT_REGISTERED');

        // Register the device carried by the default header, then it works.
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/devices/register', [
                'device_uuid' => \Database\Factories\TenantFactory::AUTO_DEVICE_UUID,
                'platform' => 'ANDROID',
            ])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/sync/products')
            ->assertOk();
    }

    public function test_existing_tenant_business_api_not_broken(): void
    {
        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();
    }
}
