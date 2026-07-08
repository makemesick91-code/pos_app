<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantManualSuspension;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionRenewal\SubscriptionRenewalRunService;
use App\Services\TenantLifecycle\TenantLifecycleService;
use App\Services\TenantLifecycle\TenantSuspensionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 25 — manual suspension has precedence over Sprint 24 subscription
 * renewal/dunning automation (TLS-R004). Running renewal evaluation never lifts
 * or overrides a manual suspension; only an explicit platform-admin lift can.
 */
class TenantLifecyclePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_evaluation_does_not_override_manual_suspension(): void
    {
        $tenant = Tenant::factory()->create();
        // Put the subscription in the renewal window so it becomes a candidate.
        $tenant->tenantSubscriptions()->update(['ends_at' => now()->addDays(5)]);

        $admin = User::factory()->platformAdmin()->create();
        app(TenantSuspensionService::class)->suspend(
            tenant: $tenant,
            actor: $admin,
            reason: 'Manual suspension before renewal run.',
            reasonCategory: 'PAYMENT_OVERDUE',
        );

        // Run the Sprint 24 renewal evaluation (awareness only).
        $runs = app(SubscriptionRenewalRunService::class);
        $run = $runs->create([]);
        $runs->evaluate($run);

        // The manual suspension is still ACTIVE and the tenant is still blocked.
        $this->assertDatabaseHas('tenant_manual_suspensions', [
            'tenant_id' => $tenant->id,
            'status' => TenantManualSuspension::STATUS_ACTIVE,
        ]);

        $decision = app(TenantLifecycleService::class)->resolve($tenant->refresh());
        $this->assertFalse($decision->allowed);
        $this->assertSame('suspended', $decision->status);
        $this->assertTrue($decision->manuallySuspended);
    }

    public function test_renewal_and_dunning_guardrails_are_disabled(): void
    {
        // TLS-R004 automation guardrails must stay false.
        $this->assertFalse((bool) config('tenant_lifecycle.dunning_can_override_manual_suspension_allowed'));
        $this->assertFalse((bool) config('tenant_lifecycle.renewal_can_override_manual_suspension_allowed'));
        $this->assertFalse((bool) config('tenant_lifecycle.auto_tenant_reactivation_allowed'));
    }

    public function test_subscription_status_active_does_not_clear_manual_suspension(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        app(TenantSuspensionService::class)->suspend(
            tenant: $tenant,
            actor: $admin,
            reason: 'Suspended despite active subscription.',
        );

        // Subscription is fully ACTIVE (factory default), yet lifecycle stays blocked.
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $tenant->currentSubscription()->status);

        $decision = app(TenantLifecycleService::class)->resolve($tenant->refresh());
        $this->assertFalse($decision->allowed);
    }
}
