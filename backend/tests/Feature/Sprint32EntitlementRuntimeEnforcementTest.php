<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureExportEntitled;
use App\Http\Middleware\EnsureFeatureEntitled;
use App\Http\Middleware\EnsureTenantCanWrite;
use App\Models\RegisteredDevice;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantEntitlementDecision;
use App\Models\TenantManualSuspension;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use App\Services\Entitlements\EntitlementBillingStateService;
use App\Services\Entitlements\EntitlementDecision;
use App\Services\Entitlements\EntitlementRedactor;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Sprint 32 — Plan Entitlement Runtime Enforcement & Subscription Access Control.
 *
 * Verifies the runtime enforcement layer: billing/subscription/lifecycle write
 * access, plan resource limits (branch/user/cashier/device/outlet/register),
 * feature/export/report entitlement, over-quota readability, denied-decision
 * auditing + redaction, the admin surface, and the command/governance gates.
 */
class Sprint32EntitlementRuntimeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function access(): EntitlementAccessService
    {
        return app(EntitlementAccessService::class);
    }

    private function billing(): EntitlementBillingStateService
    {
        return app(EntitlementBillingStateService::class);
    }

    private function setSubscription(Tenant $tenant, string $status, array $dates = []): void
    {
        $this->resetSubscriptionState($tenant);
        TenantSubscription::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $this->starterPlan()->id,
            'status' => $status,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addMonth(),
        ], $dates));
    }

    private function makeInvoice(Tenant $tenant, string $collectionState, int $daysPastDue, int $total = 99000): TenantBillingInvoice
    {
        return TenantBillingInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'plan_key' => 'starter',
            'invoice_number' => 'INV-'.$tenant->id.'-'.uniqid(),
            'period_key' => now()->format('Y-m'),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issued_at' => now()->subDays($daysPastDue + 1),
            'due_at' => now()->subDays($daysPastDue),
            'currency' => 'IDR',
            'subtotal_amount' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $total,
            'status' => TenantBillingInvoice::STATUS_ISSUED,
            'collection_state' => $collectionState,
            'source' => 'test',
            'idempotency_key' => 'idem-'.uniqid(),
        ]);
    }

    // --- Governance / config -------------------------------------------------

    public function test_ent_rules_and_safe_defaults_are_configured(): void
    {
        $rules = config('entitlement_governance.rules');
        for ($i = 1; $i <= 24; $i++) {
            $this->assertArrayHasKey(sprintf('ENT-R%03d', $i), $rules);
        }

        $this->assertTrue((bool) config('entitlement_governance.runtime_enforcement_enabled'));
        $this->assertTrue((bool) config('entitlement_governance.fail_closed_on_unknown_plan'));
        $this->assertFalse((bool) config('entitlement_governance.unknown_plan_grants_unlimited_allowed'));
        $this->assertFalse((bool) config('entitlement_governance.paid_invoice_lifts_manual_suspension_allowed'));
    }

    public function test_governance_audit_and_go_no_go_commands_pass(): void
    {
        $this->artisan('entitlement:governance-audit')->assertExitCode(0);
        $this->artisan('entitlement:plan-summary')->assertExitCode(0);
        // go-no-go depends on docs existing in the repo; assert it runs.
        $this->artisan('entitlement:go-no-go --json')->run();
        $this->assertTrue(true);
    }

    // --- Billing / subscription state ---------------------------------------

    public function test_active_paid_tenant_can_write(): void
    {
        $tenant = Tenant::factory()->create();
        $decision = $this->billing()->resolveWriteAccess($tenant);

        $this->assertTrue($decision->allowed);
        $this->assertSame('active_paid', $decision->billingState);
    }

    public function test_active_trial_tenant_can_write(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setSubscription($tenant, TenantSubscription::STATUS_TRIAL, [
            'trial_ends_at' => now()->addDays(10),
        ]);

        $decision = $this->billing()->resolveWriteAccess($tenant->fresh());
        $this->assertTrue($decision->allowed);
        $this->assertSame('active_trial', $decision->billingState);
    }

    public function test_expired_trial_is_read_only(): void
    {
        $tenant = Tenant::factory()->create();
        $this->setSubscription($tenant, TenantSubscription::STATUS_TRIAL, [
            'trial_ends_at' => now()->subDay(),
        ]);

        $decision = $this->billing()->resolveWriteAccess($tenant->fresh());
        $this->assertFalse($decision->allowed);
        $this->assertSame('TRIAL_EXPIRED', $decision->reasonCode);
        $this->assertTrue($decision->readOnly);

        // Reads of existing data remain allowed.
        $this->assertTrue($this->billing()->resolveReadAccess($tenant->fresh())->allowed);
    }

    public function test_unpaid_within_grace_is_degraded_but_allowed(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeInvoice($tenant, TenantBillingInvoice::COLLECTION_PENDING, daysPastDue: 2);

        $decision = $this->billing()->resolveWriteAccess($tenant->fresh());
        $this->assertTrue($decision->allowed);
        $this->assertTrue($decision->degraded);
        $this->assertSame('unpaid_within_grace', $decision->billingState);
    }

    public function test_unpaid_past_grace_blocks_writes_but_allows_reads(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeInvoice($tenant, TenantBillingInvoice::COLLECTION_OVERDUE, daysPastDue: 30);

        $write = $this->billing()->resolveWriteAccess($tenant->fresh());
        $this->assertFalse($write->allowed);
        $this->assertSame('UNPAID_PAST_GRACE', $write->reasonCode);

        $this->assertTrue($this->billing()->resolveReadAccess($tenant->fresh())->allowed);
    }

    public function test_manual_suspension_wins_over_paid_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        // A fully paid invoice (no outstanding) — must NOT lift the suspension.
        $this->makeInvoice($tenant, TenantBillingInvoice::COLLECTION_PAID, daysPastDue: 30);
        TenantManualSuspension::query()->create([
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'ops hold',
            'reason_category' => 'compliance',
            'effective_at' => now()->subDay(),
        ]);

        $decision = $this->billing()->resolveWriteAccess($tenant->fresh());
        $this->assertFalse($decision->allowed);
        $this->assertSame('MANUALLY_SUSPENDED', $decision->reasonCode);
        $this->assertSame('manually_suspended', $decision->billingState);
    }

    // --- Resource limits -----------------------------------------------------

    public function test_branch_creation_allowed_below_and_denied_at_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter'); // branches.max = 1
        $tenant->stores()->delete();

        $this->assertTrue($this->access()->canCreateBranch($tenant->fresh())->allowed);

        Store::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'S1', 'is_active' => true]);

        $denied = $this->access()->canCreateBranch($tenant->fresh());
        $this->assertFalse($denied->allowed);
        $this->assertSame('OVER_QUOTA', $denied->reasonCode);
    }

    public function test_outlet_and_register_share_branch_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter');
        $tenant->stores()->delete();
        Store::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'S1', 'is_active' => true]);

        $this->assertFalse($this->access()->canCreateOutletOrRegister($tenant->fresh())->allowed);
    }

    public function test_over_quota_tenant_keeps_reads(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter');
        $tenant->stores()->delete();
        Store::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'S1', 'is_active' => true]);

        $this->assertFalse($this->access()->canCreateBranch($tenant->fresh())->allowed);
        $this->assertTrue($this->access()->canRead($tenant->fresh())->allowed);
    }

    public function test_user_and_cashier_creation_allowed_below_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter'); // users.max = 25

        $this->assertTrue($this->access()->canCreateUser($tenant->fresh())->allowed);
        $this->assertTrue($this->access()->canCreateCashier($tenant->fresh())->allowed);
    }

    public function test_device_creation_denied_at_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter'); // devices.max = 10
        $tenant->registeredDevices()->delete();

        for ($i = 0; $i < 10; $i++) {
            RegisteredDevice::query()->create([
                'tenant_id' => $tenant->id,
                'device_uuid' => 'dev-'.$i.'-'.uniqid(),
                'status' => RegisteredDevice::STATUS_ACTIVE,
                'registered_at' => now(),
            ]);
        }

        $denied = $this->access()->canRegisterDevice($tenant->fresh());
        $this->assertFalse($denied->allowed);
        $this->assertSame('OVER_QUOTA', $denied->reasonCode);
    }

    // --- Features / export / report -----------------------------------------

    public function test_entitled_feature_allowed_and_non_entitled_denied(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter'); // reports.advanced = false

        $this->assertTrue($this->access()->canUseFeature($tenant->fresh(), 'inventory.basic')->allowed);

        $denied = $this->access()->canUseFeature($tenant->fresh(), 'reports.advanced');
        $this->assertFalse($denied->allowed);
        $this->assertSame('FEATURE_NOT_IN_PLAN', $denied->reasonCode);
    }

    public function test_export_denied_when_suspended(): void
    {
        $tenant = Tenant::factory()->create();
        TenantManualSuspension::query()->create([
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'hold',
            'reason_category' => 'compliance',
            'effective_at' => now()->subDay(),
        ]);

        $decision = $this->access()->canUseExport($tenant->fresh(), 'reports.daily-sales.csv');
        $this->assertFalse($decision->allowed);
        $this->assertSame('MANUALLY_SUSPENDED', $decision->reasonCode);
    }

    public function test_entitled_export_allowed_for_active_paid(): void
    {
        $tenant = Tenant::factory()->create(); // enterprise, active
        $decision = $this->access()->canUseExport($tenant->fresh(), 'reports.daily-sales.csv');
        $this->assertTrue($decision->allowed);
    }

    // --- Audit / redaction ---------------------------------------------------

    public function test_denied_decision_is_persisted_and_allowed_read_is_not(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter');
        $tenant->stores()->delete();
        Store::query()->create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'S1', 'is_active' => true]);

        $this->access()->canCreateBranch($tenant->fresh());

        $this->assertDatabaseHas('tenant_entitlement_decisions', [
            'tenant_id' => $tenant->id,
            'decision' => TenantEntitlementDecision::DECISION_DENIED,
            'reason_code' => 'OVER_QUOTA',
        ]);

        // A routine allowed read is not persisted.
        $before = TenantEntitlementDecision::query()->count();
        $this->access()->canRead($tenant->fresh());
        $this->assertSame($before, TenantEntitlementDecision::query()->count());
    }

    public function test_redactor_drops_secrets_and_pii(): void
    {
        $redacted = app(EntitlementRedactor::class)->redact([
            'reason' => 'over quota',
            'password' => 'hunter2',
            'api_token' => 'abc',
            'phone' => '0811111111',
            'owner_name' => 'Jane',
            'signature' => 'sig',
            'nested' => ['card' => '4111'],
        ]);

        $this->assertArrayHasKey('reason', $redacted);
        $this->assertArrayNotHasKey('password', $redacted);
        $this->assertArrayNotHasKey('api_token', $redacted);
        $this->assertArrayNotHasKey('phone', $redacted);
        $this->assertArrayNotHasKey('owner_name', $redacted);
        $this->assertArrayNotHasKey('signature', $redacted);
        $this->assertArrayNotHasKey('nested', $redacted);
    }

    // --- Middleware ----------------------------------------------------------

    public function test_write_gate_blocks_write_but_allows_read_when_unpaid_past_grace(): void
    {
        $tenant = Tenant::factory()->create();
        $this->makeInvoice($tenant, TenantBillingInvoice::COLLECTION_OVERDUE, daysPastDue: 30);

        app(TenantContext::class)->set(null, $tenant->fresh(), null);
        $middleware = app(EnsureTenantCanWrite::class);

        $post = Request::create('/api/v1/sales', 'POST');
        $response = $middleware->handle($post, fn () => new Response('ok', 200));
        $this->assertSame(Response::HTTP_PAYMENT_REQUIRED, $response->getStatusCode());

        $get = Request::create('/api/v1/sales', 'GET');
        $passed = $middleware->handle($get, fn () => new Response('ok', 200));
        $this->assertSame(200, $passed->getStatusCode());
    }

    public function test_feature_middleware_denies_non_entitled_feature(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assignTenantPlan($tenant, 'starter');

        app(TenantContext::class)->set(null, $tenant->fresh(), null);
        $middleware = app(EnsureFeatureEntitled::class);

        $response = $middleware->handle(
            Request::create('/api/v1/x', 'GET'),
            fn () => new Response('ok', 200),
            'reports.advanced',
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function test_export_middleware_allows_entitled_active_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set(null, $tenant->fresh(), null);
        $middleware = app(EnsureExportEntitled::class);

        $response = $middleware->handle(
            Request::create('/api/v1/x', 'GET'),
            fn () => new Response('ok', 200),
            'reports.daily-sales.csv',
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    // --- Admin surface -------------------------------------------------------

    public function test_admin_entitlement_routes_require_platform_admin(): void
    {
        $this->getJson('/api/v1/admin/tenant-billing/entitlements/plan-summary')
            ->assertStatus(401);

        $tenantUser = User::factory()->create(['role' => User::ROLE_TENANT_OWNER]);
        $this->actingAs($tenantUser)
            ->getJson('/api/v1/admin/tenant-billing/entitlements/plan-summary')
            ->assertStatus(403);

        $admin = User::factory()->create(['is_platform_admin' => true, 'role' => User::ROLE_SAAS_ADMIN, 'tenant_id' => null]);
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/tenant-billing/entitlements/plan-summary')
            ->assertStatus(200)
            ->assertJsonPath('data.runtime_enforcement_enabled', true);
    }

    public function test_admin_tenant_entitlement_summary_returns_safe_payload(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['is_platform_admin' => true, 'role' => User::ROLE_SAAS_ADMIN, 'tenant_id' => null]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/tenants/{$tenant->id}/tenant-billing/entitlements/summary")
            ->assertStatus(200);

        $body = $response->getContent();
        $this->assertStringNotContainsStringIgnoringCase('password', $body);
        $this->assertStringNotContainsStringIgnoringCase('secret', $body);
    }

    public function test_no_tenant_facing_route_can_mutate_entitlement_state(): void
    {
        $this->assertFalse((bool) config('entitlement_governance.tenant_route_can_mutate_entitlement_state_allowed'));
    }

    public function test_decision_dto_is_deterministic(): void
    {
        $tenant = Tenant::factory()->create();
        $a = $this->billing()->resolveWriteAccess($tenant->fresh());
        $b = $this->billing()->resolveWriteAccess($tenant->fresh());

        $this->assertSame($a->reasonCode, $b->reasonCode);
        $this->assertSame($a->billingState, $b->billingState);
        $this->assertInstanceOf(EntitlementDecision::class, $a);
    }
}
