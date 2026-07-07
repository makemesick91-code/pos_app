<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 10 — GET /api/v1/subscription/status. The allowed/blocked decision is
 * backend-computed from the subscription dates and always reachable (it is not
 * behind the subscription/device gate) so Android can render a blocked state.
 */
class SubscriptionStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['code' => 'TENANT-A']);
        Store::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'A1']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    private function replaceSubscription(string $stateMethod): void
    {
        $this->tenant->tenantSubscriptions()->delete();
        TenantSubscription::factory()->{$stateMethod}()->create([
            'tenant_id' => $this->tenant->id,
            'subscription_plan_id' => $this->starterPlan()->id,
        ]);
    }

    public function test_active_subscription_is_allowed_with_plan_and_device_count(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_ACTIVE)
            ->assertJsonPath('data.plan.code', SubscriptionPlan::CODE_STARTER)
            ->assertJsonPath('data.plan.max_devices', 3)
            ->assertJsonPath('data.devices.active_count', 1)
            ->assertJsonPath('data.devices.max_devices', 3);
    }

    public function test_trial_is_allowed_before_trial_end(): void
    {
        $this->replaceSubscription('trial');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_TRIAL);
    }

    public function test_grace_is_allowed_before_grace_end(): void
    {
        $this->replaceSubscription('grace');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_GRACE);
    }

    public function test_expired_is_blocked(): void
    {
        $this->replaceSubscription('expired');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_EXPIRED);
    }

    public function test_cancelled_is_blocked(): void
    {
        $this->replaceSubscription('cancelled');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_CANCELLED);
    }

    public function test_suspended_is_blocked(): void
    {
        $this->replaceSubscription('suspended');

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_SUSPENDED);
    }

    public function test_missing_subscription_is_blocked(): void
    {
        $this->tenant->tenantSubscriptions()->delete();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/subscription/status')
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.plan', null);
    }
}
