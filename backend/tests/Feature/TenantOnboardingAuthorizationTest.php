<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — onboarding APIs are platform-admin only. Unauthenticated users and
 * tenant business users can never reach them, and a tenant user can never create
 * a tenant through onboarding.
 */
class TenantOnboardingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = SubscriptionPlan::factory()->starter()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'onboarding_reference' => 'authz-001',
            'tenant_name' => 'Authz Tenant',
            'tenant_code' => 'authz-tenant',
            'owner_name' => 'Authz Owner',
            'owner_email' => 'authz.owner@example.test',
            'owner_password' => 'temporary-password',
            'subscription_plan_id' => $this->plan->id,
        ];
    }

    public function test_unauthenticated_user_cannot_onboard(): void
    {
        $this->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertStatus(401);
    }

    public function test_tenant_user_cannot_onboard(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');

        $this->assertSame(0, Tenant::query()->where('code', 'authz-tenant')->count());
    }

    public function test_tenant_user_cannot_read_onboarding_list_or_status(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_CASHIER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/tenant-onboarding')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/onboarding-status")
            ->assertStatus(403);
    }

    public function test_platform_admin_can_access_onboarding_apis(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/tenant-onboarding')
            ->assertOk();
    }
}
