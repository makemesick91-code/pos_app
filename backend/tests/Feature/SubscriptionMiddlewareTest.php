<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 10 — the subscription.active middleware guards protected business APIs.
 * An allowed subscription passes; an expired/cancelled/suspended one is blocked
 * with a stable code. Login and the subscription status endpoint are never
 * blocked by it.
 */
class SubscriptionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function expireSubscription(): void
    {
        $this->tenant->tenantSubscriptions()->delete();
        TenantSubscription::factory()->expired()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_plan_id' => $this->starterPlan()->id,
        ]);
    }

    public function test_protected_business_api_allowed_when_subscription_active(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertOk();
    }

    public function test_protected_business_api_blocked_when_subscription_expired(): void
    {
        $this->expireSubscription();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/current-stock')
            ->assertStatus(402)
            ->assertJsonPath('code', 'SUBSCRIPTION_INACTIVE')
            ->assertJsonPath('status', TenantSubscription::STATUS_EXPIRED);
    }

    public function test_reports_api_blocked_when_subscription_expired(): void
    {
        $this->expireSubscription();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/payment-summary')
            ->assertStatus(402)
            ->assertJsonPath('code', 'SUBSCRIPTION_INACTIVE');
    }

    public function test_subscription_status_endpoint_is_not_blocked(): void
    {
        $this->expireSubscription();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', false);
    }

    public function test_login_endpoint_is_not_blocked(): void
    {
        $this->expireSubscription();
        $this->user->forceFill(['password' => bcrypt('secret123')])->save();

        // login is public; an expired subscription does not block issuing a token.
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token']);
    }
}
