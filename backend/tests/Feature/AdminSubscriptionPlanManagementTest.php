<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 11 — admin subscription plan management: list/create/update/deactivate.
 * No hard delete. Code is unique; limits are positive. Mutations are audit-logged.
 */
class AdminSubscriptionPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->platformAdmin()->create();
    }

    public function test_platform_admin_can_list_plans(): void
    {
        SubscriptionPlan::factory()->starter()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/subscription-plans')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'max_devices', 'max_stores', 'is_active']]]);
    }

    public function test_platform_admin_can_create_plan(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/subscription-plans', [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly' => 499000,
                'max_stores' => 10,
                'max_devices' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'enterprise');

        $this->assertDatabaseHas('subscription_plans', ['code' => 'enterprise']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => AdminAuditLog::ACTION_PLAN_CREATED]);
    }

    public function test_plan_code_must_be_unique(): void
    {
        SubscriptionPlan::factory()->create(['code' => 'dup']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/subscription-plans', [
                'code' => 'dup',
                'name' => 'Duplicate',
                'max_stores' => 1,
                'max_devices' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_plan_limits_must_be_positive(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/subscription-plans', [
                'code' => 'bad',
                'name' => 'Bad',
                'max_stores' => 0,
                'max_devices' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_stores', 'max_devices']);
    }

    public function test_platform_admin_can_update_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/subscription-plans/{$plan->id}", [
                'name' => 'New Name',
                'max_devices' => 7,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.max_devices', 7);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => AdminAuditLog::ACTION_PLAN_UPDATED]);
    }

    public function test_platform_admin_can_deactivate_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/admin/subscription-plans/{$plan->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id, 'is_active' => false]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => AdminAuditLog::ACTION_PLAN_DEACTIVATED]);
    }

    public function test_there_is_no_hard_delete_endpoint(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/subscription-plans/{$plan->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
    }
}
