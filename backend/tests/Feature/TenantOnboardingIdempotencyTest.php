<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 12 — onboarding is idempotent by onboarding_reference: a replayed
 * request returns the existing run (idempotent_replay=true) and never creates a
 * duplicate tenant, store, owner user, or subscription.
 */
class TenantOnboardingIdempotencyTest extends TestCase
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
    private function payload(): array
    {
        return [
            'onboarding_reference' => 'tenant-idem-001',
            'tenant_name' => 'Toko Idempoten',
            'tenant_code' => 'demo-idem',
            'store_name' => 'Toko Idem Pusat',
            'owner_name' => 'Owner Idem',
            'owner_email' => 'owner.idem@example.test',
            'owner_password' => 'temporary-password',
            'subscription_plan_id' => $this->plan->id,
            'subscription_status' => 'TRIAL',
            'demo_data_enabled' => true,
        ];
    }

    public function test_duplicate_reference_replays_without_duplicating_records(): void
    {
        $first = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertCreated()
            ->assertJsonPath('meta.idempotent_replay', false);

        $second = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/tenant-onboarding', $this->payload())
            ->assertOk()
            ->assertJsonPath('meta.idempotent_replay', true);

        // Same run returned.
        $this->assertSame(
            $first->json('data.id'),
            $second->json('data.id'),
        );

        $tenant = Tenant::query()->where('code', 'demo-idem')->firstOrFail();

        $this->assertSame(1, Tenant::query()->where('code', 'demo-idem')->count());
        $this->assertSame(1, User::query()->where('email', 'owner.idem@example.test')->count());
        $this->assertSame(1, Store::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, TenantSubscription::query()->where('tenant_id', $tenant->id)->count());
    }
}
