<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantManualSuspension;
use App\Models\TenantPlanAssignment;
use App\Models\TenantProvisioningRun;
use App\Models\TenantProvisioningStep;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use App\Services\TenantOnboarding\FirstBranchProvisioningService;
use App\Services\TenantOnboarding\OnboardingException;
use App\Services\TenantOnboarding\OnboardingRequestData;
use App\Services\TenantOnboarding\TenantOnboardingService;
use App\Services\TenantOnboarding\TrialToPaidReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint33TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TenantOnboardingService
    {
        return app(TenantOnboardingService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requestData(array $overrides = []): OnboardingRequestData
    {
        return OnboardingRequestData::fromArray(array_merge([
            'idempotency_key' => 'onb-'.uniqid(),
            'plan_code' => 'starter',
            'tenant_name' => 'Toko Budi',
            'owner_name' => 'Budi',
            'first_branch_name' => 'Pusat',
            'with_cashier' => true,
            'with_register' => true,
            'onboarding_type' => 'platform_admin',
        ], $overrides));
    }

    public function test_dry_run_does_not_mutate(): void
    {
        $preview = $this->service()->dryRun($this->requestData(['idempotency_key' => '']));

        $this->assertTrue($preview['dry_run']);
        $this->assertSame('starter', $preview['plan_code']);
        $this->assertSame(0, TenantProvisioningRun::count());
        $this->assertSame(0, Tenant::count());
    }

    public function test_execute_creates_full_tenant(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-full-1']));

        $this->assertSame(TenantProvisioningRun::STATUS_COMPLETED, $run->status);
        $this->assertNotNull($run->tenant_id);
        $this->assertSame('starter', $run->resolved_plan_code);
        $this->assertSame(1, Tenant::count());
        $this->assertSame(1, Store::count());
        $this->assertSame(2, User::count()); // owner + cashier
        $this->assertSame(1, TenantSubscription::count());
        $this->assertSame(1, TenantPlanAssignment::where('status', TenantPlanAssignment::STATUS_ACTIVE)->count());
        $this->assertTrue(ProductCategory::where('tenant_id', $run->tenant_id)->exists());
    }

    public function test_trial_is_time_bounded(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-trial-1']));

        $this->assertNotNull($run->trial_starts_at);
        $this->assertNotNull($run->trial_ends_at);
        $this->assertTrue($run->trial_ends_at->greaterThan($run->trial_starts_at));

        $sub = TenantSubscription::first();
        $this->assertSame(TenantSubscription::STATUS_TRIAL, $sub->status);
    }

    public function test_idempotency_key_prevents_duplicate_and_replays(): void
    {
        $data = $this->requestData(['idempotency_key' => 'onb-idem-1']);

        $first = $this->service()->execute($data);
        $second = $this->service()->execute($data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, TenantProvisioningRun::count());
        $this->assertSame(1, Tenant::count());
        $this->assertSame(1, Store::count());
        $this->assertSame(2, User::count());
    }

    public function test_unknown_plan_fails_closed(): void
    {
        $this->expectException(OnboardingException::class);
        $this->expectExceptionMessage('not a known plan');

        $this->service()->execute($this->requestData([
            'idempotency_key' => 'onb-unknown-1',
            'plan_code' => 'diamond',
        ]));

        $this->assertSame(0, Tenant::count());
    }

    public function test_public_self_signup_type_is_rejected_by_default(): void
    {
        $this->expectException(OnboardingException::class);

        $this->service()->execute($this->requestData([
            'idempotency_key' => 'onb-signup-1',
            'onboarding_type' => 'approved_signup',
        ]));
    }

    public function test_checklist_marks_complete_when_required_steps_done(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-check-1']));

        $checklist = $run->checklist_json;
        $this->assertTrue($checklist['complete']);
        $this->assertTrue($checklist['items']['tenant_created']['done']);
        $this->assertTrue($checklist['items']['first_branch_created']['done']);
        $this->assertTrue($checklist['items']['owner_admin_created']['done']);
        $this->assertTrue($checklist['items']['entitlement_runtime_access_verified']['done']);
    }

    public function test_every_step_is_audited(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-audit-1']));

        $steps = TenantProvisioningStep::where('tenant_provisioning_run_id', $run->id)->get();
        $this->assertGreaterThanOrEqual(8, $steps->count());

        // Every completed step has a reason code and a redacted metadata array.
        foreach ($steps as $step) {
            $this->assertNotNull($step->reason_code);
            $this->assertIsArray($step->metadata_json);
        }
    }

    public function test_step_metadata_contains_no_pii_or_secrets(): void
    {
        $run = $this->service()->execute($this->requestData([
            'idempotency_key' => 'onb-pii-1',
            'owner_email' => 'secret.owner@example.com',
            'owner_phone' => '081234567890',
        ]));

        $blob = strtolower(json_encode(TenantProvisioningStep::where('tenant_provisioning_run_id', $run->id)->pluck('metadata_json')));

        $this->assertStringNotContainsString('secret.owner@example.com', $blob);
        $this->assertStringNotContainsString('081234567890', $blob);
        $this->assertStringNotContainsString('password', $blob);
    }

    public function test_owner_password_is_never_stored_in_plaintext(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-pw-1']));

        $owner = User::find($run->owner_user_id);
        $this->assertNotNull($owner);
        // Hashed (bcrypt/argon) — never a readable password.
        $this->assertStringStartsWith('$', $owner->password);
    }

    public function test_first_branch_denied_when_entitlement_blocks_and_is_audited(): void
    {
        // Fresh tenant with NO subscription -> billing state denies writes.
        $tenant = Tenant::create(['code' => 'BLK-1', 'name' => 'Blocked', 'status' => Tenant::STATUS_ACTIVE]);
        $before = \App\Models\TenantEntitlementDecision::count();

        try {
            app(FirstBranchProvisioningService::class)->provision($tenant, $this->requestData(), null);
            $this->fail('Expected an OnboardingException.');
        } catch (OnboardingException $e) {
            $this->assertSame('DENIED_ENTITLEMENT', $e->reasonCode);
        }

        $this->assertGreaterThan($before, \App\Models\TenantEntitlementDecision::count());
        $this->assertSame(0, $tenant->stores()->count());
    }

    public function test_manual_suspension_wins_over_trial(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-susp-1']));
        $tenant = Tenant::find($run->tenant_id);

        TenantManualSuspension::create([
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
            'reason' => 'governed test suspension',
            'effective_at' => now(),
        ]);

        $decision = app(EntitlementAccessService::class)->canCreateBranch($tenant->refresh());

        $this->assertTrue($decision->denied());
        $this->assertSame('MANUALLY_SUSPENDED', $decision->reasonCode);
    }

    public function test_trial_to_paid_invoice_does_not_mark_paid(): void
    {
        $run = $this->service()->execute($this->requestData([
            'idempotency_key' => 'onb-inv-1',
            'with_invoice' => true,
        ]));

        $this->assertNotNull($run->tenant_billing_invoice_id);

        $invoice = \App\Models\TenantBillingInvoice::find($run->tenant_billing_invoice_id);
        $summary = app(TrialToPaidReadinessService::class)->summary($invoice);

        $this->assertFalse($summary['is_paid']);
        $this->assertNotSame(\App\Models\TenantBillingInvoice::COLLECTION_PAID, $invoice->collection_state);
    }

    public function test_payment_intent_uses_gateway_service(): void
    {
        $run = $this->service()->execute($this->requestData([
            'idempotency_key' => 'onb-intent-1',
            'with_invoice' => true,
            'with_payment_intent' => true,
        ]));

        $this->assertNotNull($run->payment_intent_id);
        $this->assertTrue(
            \App\Models\TenantBillingPaymentIntent::whereKey($run->payment_intent_id)->exists()
        );
    }

    public function test_cancel_is_allowed_only_in_safe_states(): void
    {
        $run = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-cancel-1']));

        // A completed run cannot be cancelled.
        $this->expectException(OnboardingException::class);
        $this->service()->cancel($run->refresh());
    }

    public function test_seed_data_is_tenant_isolated(): void
    {
        $a = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-iso-a', 'tenant_name' => 'A']));
        $b = $this->service()->execute($this->requestData(['idempotency_key' => 'onb-iso-b', 'tenant_name' => 'B']));

        $this->assertNotSame($a->tenant_id, $b->tenant_id);
        $this->assertSame(3, ProductCategory::where('tenant_id', $a->tenant_id)->count());
        $this->assertSame(3, ProductCategory::where('tenant_id', $b->tenant_id)->count());
        // No category leaks across tenants.
        $this->assertFalse(
            ProductCategory::where('tenant_id', $a->tenant_id)
                ->whereIn('id', ProductCategory::where('tenant_id', $b->tenant_id)->pluck('id'))
                ->exists()
        );
    }
}
