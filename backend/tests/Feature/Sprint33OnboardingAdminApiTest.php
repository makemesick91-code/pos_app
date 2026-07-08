<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantProvisioningRun;
use App\Models\User;
use App\Services\TenantOnboarding\OnboardingRequestData;
use App\Services\TenantOnboarding\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint33OnboardingAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/admin/tenant-billing/onboarding';

    private function admin(): User
    {
        return User::factory()->platformAdmin()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => 'api-'.uniqid(),
            'plan_code' => 'starter',
            'tenant_name' => 'API Toko',
            'owner_name' => 'Budi',
            'owner_email' => 'owner.pii@example.com',
            'first_branch_name' => 'Pusat',
            'with_cashier' => true,
            'with_register' => true,
        ], $overrides);
    }

    public function test_start_requires_platform_admin(): void
    {
        $tenantUser = User::factory()->tenantOwner()->create();

        $this->actingAs($tenantUser, 'sanctum')
            ->postJson(self::BASE, $this->payload())
            ->assertForbidden();
    }

    public function test_unauthenticated_is_blocked(): void
    {
        $this->postJson(self::BASE, $this->payload())->assertUnauthorized();
    }

    public function test_admin_can_start_onboarding(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson(self::BASE, $this->payload(['idempotency_key' => 'api-start-1']))
            ->assertCreated();

        $response->assertJsonPath('data.status', TenantProvisioningRun::STATUS_COMPLETED);
        $this->assertSame(1, Tenant::count());
    }

    public function test_response_does_not_leak_owner_pii(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson(self::BASE, $this->payload(['idempotency_key' => 'api-pii-1']));

        $this->assertStringNotContainsString('owner.pii@example.com', $response->getContent());
    }

    public function test_unknown_plan_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson(self::BASE, $this->payload(['plan_code' => 'diamond']))
            ->assertStatus(422);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['idempotency_key']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson(self::BASE, $payload)
            ->assertStatus(422);
    }

    public function test_retry_is_idempotent(): void
    {
        $admin = $this->admin();
        $payload = $this->payload(['idempotency_key' => 'api-retry-1']);

        $created = $this->actingAs($admin, 'sanctum')->postJson(self::BASE, $payload)->assertCreated();
        $runId = $created->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson(self::BASE.'/'.$runId.'/retry', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $runId);

        $this->assertSame(1, Tenant::count());
    }

    public function test_checklist_endpoint_returns_deterministic_items(): void
    {
        $run = app(TenantOnboardingService::class)->execute(
            OnboardingRequestData::fromArray($this->payload(['idempotency_key' => 'api-cl-1']))
        );

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson(self::BASE.'/'.$run->id.'/checklist')
            ->assertOk()
            ->assertJsonPath('data.complete', true);
    }

    public function test_index_lists_runs(): void
    {
        app(TenantOnboardingService::class)->execute(
            OnboardingRequestData::fromArray($this->payload(['idempotency_key' => 'api-idx-1']))
        );

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson(self::BASE)
            ->assertOk()
            ->assertJsonStructure(['data', 'links']);
    }

    public function test_governance_endpoint_is_read_only_summary(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson(self::BASE.'/governance')
            ->assertOk()
            ->assertJsonStructure(['data' => ['rules', 'governance', 'go_no_go', 'plan_readiness']]);
    }

    public function test_invoice_then_payment_intent_flow(): void
    {
        $admin = $this->admin();
        $run = app(TenantOnboardingService::class)->execute(
            OnboardingRequestData::fromArray($this->payload(['idempotency_key' => 'api-inv-1']))
        );

        $this->actingAs($admin, 'sanctum')
            ->postJson(self::BASE.'/'.$run->id.'/invoice')
            ->assertOk()
            ->assertJsonPath('data.is_paid', false);

        $this->actingAs($admin, 'sanctum')
            ->postJson(self::BASE.'/'.$run->id.'/payment-intent')
            ->assertOk()
            ->assertJsonStructure(['data' => ['payment_intent_id', 'status']]);
    }

    public function test_no_public_onboarding_mutation_route_exists(): void
    {
        // A public (unauthenticated, non-admin) POST to any onboarding path is
        // never accepted as a tenant/self-signup mutation.
        $this->postJson('/api/v1/onboarding', $this->payload())->assertStatus(404);
        $this->postJson('/api/v1/tenant-billing/onboarding', $this->payload())->assertStatus(404);
    }
}
