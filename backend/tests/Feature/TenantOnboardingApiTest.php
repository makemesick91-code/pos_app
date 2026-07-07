<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — platform-admin tenant onboarding creates a tenant, default store,
 * owner user, and subscription in one transaction, with optional demo data. The
 * checklist is backend-derived and the owner password is never returned.
 */
class TenantOnboardingApiTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'onboarding_reference' => 'tenant-demo-001',
            'tenant_name' => 'Toko Demo Aish',
            'tenant_code' => 'demo-aish',
            'store_name' => 'Toko Demo Pusat',
            'owner_name' => 'Owner Demo',
            'owner_email' => 'owner.demo@example.test',
            'owner_password' => 'temporary-password',
            'subscription_plan_id' => $this->plan->id,
            'subscription_status' => 'TRIAL',
            'trial_days' => 14,
            'demo_data_enabled' => true,
        ], $overrides);
    }

    public function test_platform_admin_can_onboard_tenant_with_demo_data(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('meta.idempotent_replay', false)
            ->assertJsonPath('data.owner_user.email', 'owner.demo@example.test')
            ->assertJsonPath('data.checklist.tenant_created', true)
            ->assertJsonPath('data.checklist.default_store_created', true)
            ->assertJsonPath('data.checklist.owner_user_created', true)
            ->assertJsonPath('data.checklist.subscription_assigned', true)
            ->assertJsonPath('data.checklist.demo_products_seeded', true)
            ->assertJsonPath('data.checklist.opening_inventory_seeded', true);

        // Password must never be exposed in the response.
        $response->assertDontSee('temporary-password');

        $tenant = Tenant::query()->where('code', 'demo-aish')->firstOrFail();

        $this->assertDatabaseHas('stores', ['tenant_id' => $tenant->id, 'name' => 'Toko Demo Pusat']);
        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'owner.demo@example.test',
            'role' => User::ROLE_TENANT_OWNER,
        ]);
        $this->assertDatabaseHas('tenant_subscriptions', [
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $this->plan->id,
        ]);
        $this->assertTrue(
            InventoryMovement::query()
                ->where('tenant_id', $tenant->id)
                ->where('movement_type', InventoryMovement::TYPE_OPENING)
                ->exists(),
            'Opening inventory movements must be seeded via the ledger.',
        );
    }

    public function test_onboarding_without_demo_data_skips_catalog(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload([
                'onboarding_reference' => 'tenant-demo-nodemo',
                'tenant_code' => 'demo-nodemo',
                'owner_email' => 'nodemo@example.test',
                'demo_data_enabled' => false,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.checklist.demo_products_seeded', false);

        $tenant = Tenant::query()->where('code', 'demo-nodemo')->firstOrFail();
        $this->assertSame(0, $tenant->products()->count());
    }

    public function test_password_is_not_stored_in_run_metadata(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertCreated();

        $run = \App\Models\TenantOnboardingRun::query()->firstOrFail();
        $this->assertStringNotContainsString('temporary-password', json_encode($run->metadata ?? []));
        $this->assertStringNotContainsString('temporary-password', json_encode($run->checklist ?? []));
    }

    public function test_onboarding_validation_rejects_missing_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', [
                'onboarding_reference' => 'x',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_name', 'tenant_code', 'owner_email', 'owner_password', 'subscription_plan_id']);
    }
}
