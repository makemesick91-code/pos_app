<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — admin subscription assignment/update. Platform admin manages the
 * subscription foundation (plan + status/date window). Every mutation is
 * audit-logged. No real billing is performed.
 */
class AdminSubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_assign_plan_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->pro()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscriptions", [
                'subscription_plan_id' => $plan->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->addMonth()->toIso8601String(),
                'notes' => 'Manual admin assignment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.subscription_plan_id', $plan->id)
            ->assertJsonPath('data.status', TenantSubscription::STATUS_ACTIVE);

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $this->admin->id,
            'action' => AdminAuditLog::ACTION_SUBSCRIPTION_ASSIGNED,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_platform_admin_can_update_tenant_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = $tenant->currentSubscription();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/tenants/{$tenant->id}/subscriptions/{$subscription->id}", [
                'status' => TenantSubscription::STATUS_SUSPENDED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', TenantSubscription::STATUS_SUSPENDED);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => AdminAuditLog::ACTION_SUBSCRIPTION_UPDATED,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_subscription_update_must_belong_to_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $subscriptionB = $tenantB->currentSubscription();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/tenants/{$tenantA->id}/subscriptions/{$subscriptionB->id}", [
                'status' => TenantSubscription::STATUS_CANCELLED,
            ])
            ->assertStatus(404);
    }

    public function test_invalid_plan_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscriptions", [
                'subscription_plan_id' => 999999,
                'status' => TenantSubscription::STATUS_ACTIVE,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subscription_plan_id');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscriptions", [
                'subscription_plan_id' => $plan->id,
                'status' => 'NONSENSE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_date_window_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscriptions", [
                'subscription_plan_id' => $plan->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->subMonth()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_tenant_user_cannot_assign_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => User::ROLE_TENANT_OWNER,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/tenants/{$tenant->id}/subscriptions", [
                'subscription_plan_id' => $plan->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'PLATFORM_ADMIN_REQUIRED');
    }
}
