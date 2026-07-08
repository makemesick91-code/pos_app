<?php

namespace App\Services\Entitlements;

use App\Models\Tenant;
use App\Models\TenantBillingInvoice;
use App\Models\TenantSubscription;
use App\Services\Subscriptions\SubscriptionStatusService;
use Illuminate\Support\Carbon;

/**
 * Sprint 32 — resolves a tenant's authoritative BILLING/SUBSCRIPTION access state
 * into a single deterministic write-access decision (ENT-R011..R016, R019).
 *
 * Integrates, in strict precedence:
 *   1. Manual suspension (Sprint 25) — always wins (ENT-R013). A paid invoice
 *      never lifts it (ENT-R014); only a governed lifecycle service can.
 *   2. Subscription status (Sprint 10) — trial / active / grace / expired /
 *      cancelled, recomputed server-side (never trusted from the client).
 *   3. Outstanding billing invoices (Sprint 30) — unpaid within grace (degraded,
 *      writes allowed) vs unpaid past grace (read-only). Settlement state
 *      (Sprint 31) is only ever consulted through the trusted collection layer
 *      that produced the invoice collection_state, so a failed/expired/cancelled
 *      provider event can never unlock writes.
 *
 * Reads of existing data are never blocked here (ENT-R017) unless a hard
 * suspended/expired read policy is explicitly enabled in config.
 */
class EntitlementBillingStateService
{
    public function __construct(
        private readonly SubscriptionStatusService $subscriptions,
    ) {}

    /**
     * The write-access decision from the billing/subscription/lifecycle dimension
     * only (usage limits and feature entitlement are layered on by
     * EntitlementAccessService).
     */
    public function resolveWriteAccess(Tenant $tenant): EntitlementDecision
    {
        $now = Carbon::now();
        $access = (array) config('entitlement_governance.access', []);

        // 1. Manual suspension (Sprint 25) — highest precedence (ENT-R013/R014).
        if ($tenant->activeManualSuspension() !== null) {
            $readOnly = ! (bool) ($access['block_reads_when_suspended'] ?? false);

            return $this->decision(
                allowed: false,
                status: EntitlementDecision::STATUS_READ_ONLY,
                reasonCode: 'MANUALLY_SUSPENDED',
                billingState: 'manually_suspended',
                subscriptionState: $this->subscriptionStateLabel($tenant),
                readOnly: $readOnly,
            );
        }

        // 2. Subscription status (Sprint 10), recomputed server-side.
        $status = $this->subscriptions->resolve($tenant);
        $subscription = $tenant->currentSubscription();
        $original = $subscription?->status;
        $effective = $status->status;

        if (! $status->allowed) {
            // A lapsed TRIAL is trial_expired; anything else is treated as unpaid
            // past grace / cancelled (still write-blocked, read-only).
            if ($original === TenantSubscription::STATUS_TRIAL) {
                return $this->decision(
                    allowed: false,
                    status: EntitlementDecision::STATUS_READ_ONLY,
                    reasonCode: 'TRIAL_EXPIRED',
                    billingState: 'trial_expired',
                    subscriptionState: $effective,
                    readOnly: (bool) ($access['expired_trial_read_only'] ?? true),
                );
            }

            if ($effective === TenantSubscription::STATUS_CANCELLED) {
                return $this->decision(
                    allowed: false,
                    status: EntitlementDecision::STATUS_DENIED,
                    reasonCode: 'SUBSCRIPTION_CANCELLED',
                    billingState: 'cancelled',
                    subscriptionState: $effective,
                    readOnly: false,
                );
            }

            if ($subscription === null) {
                return $this->decision(
                    allowed: false,
                    status: EntitlementDecision::STATUS_DENIED,
                    reasonCode: 'MISSING_SUBSCRIPTION',
                    billingState: 'missing_subscription',
                    subscriptionState: $effective,
                    readOnly: false,
                );
            }

            return $this->decision(
                allowed: false,
                status: EntitlementDecision::STATUS_READ_ONLY,
                reasonCode: 'UNPAID_PAST_GRACE',
                billingState: 'unpaid_past_grace',
                subscriptionState: $effective,
                readOnly: (bool) ($access['unpaid_past_grace_read_only'] ?? true),
            );
        }

        // Subscription is allowed. Trial and grace are their own degraded/allowed
        // states; active continues to the invoice check.
        if ($effective === TenantSubscription::STATUS_TRIAL) {
            return $this->decision(
                allowed: (bool) ($access['trial_allows_writes'] ?? true),
                status: EntitlementDecision::STATUS_ALLOWED,
                reasonCode: 'ALLOWED_ACTIVE_TRIAL',
                billingState: 'active_trial',
                subscriptionState: $effective,
            );
        }

        if ($effective === TenantSubscription::STATUS_GRACE) {
            return $this->graceDecision($effective);
        }

        // 3. Active subscription — resolve against outstanding billing invoices.
        return $this->resolveInvoiceState($tenant, $now, $effective);
    }

    public function resolveReadAccess(Tenant $tenant): EntitlementDecision
    {
        $access = (array) config('entitlement_governance.access', []);

        if ($tenant->activeManualSuspension() !== null && (bool) ($access['block_reads_when_suspended'] ?? false)) {
            return $this->decision(
                allowed: false,
                status: EntitlementDecision::STATUS_DENIED,
                reasonCode: 'MANUALLY_SUSPENDED',
                billingState: 'manually_suspended',
                subscriptionState: $this->subscriptionStateLabel($tenant),
                readOnly: false,
            );
        }

        return $this->decision(
            allowed: true,
            status: EntitlementDecision::STATUS_ALLOWED,
            reasonCode: 'ALLOWED_READ',
            billingState: $this->billingStateLabel($tenant),
            subscriptionState: $this->subscriptionStateLabel($tenant),
        );
    }

    /**
     * A safe billing-state label for summaries (no PII).
     */
    public function billingStateLabel(Tenant $tenant): string
    {
        return $this->resolveWriteAccess($tenant)->billingState ?? 'unknown';
    }

    private function subscriptionStateLabel(Tenant $tenant): ?string
    {
        return $this->subscriptions->resolve($tenant)->status;
    }

    private function resolveInvoiceState(Tenant $tenant, Carbon $now, string $effective): EntitlementDecision
    {
        $graceDays = (int) config('entitlement_governance.grace.unpaid_invoice_days', 7);
        $outstandingStates = (array) config('entitlement_governance.outstanding_collection_states', []);

        $invoices = TenantBillingInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantBillingInvoice::STATUS_ISSUED)
            ->whereIn('collection_state', $outstandingStates)
            ->get();

        $hasOutstanding = false;
        $pastGrace = false;

        foreach ($invoices as $invoice) {
            if ($invoice->outstandingAmount() <= 0) {
                continue;
            }

            $hasOutstanding = true;

            $due = $invoice->due_at;
            if ($due !== null && $now->greaterThan(Carbon::parse($due)->addDays($graceDays))) {
                $pastGrace = true;
            }
        }

        if ($pastGrace) {
            $access = (array) config('entitlement_governance.access', []);

            return $this->decision(
                allowed: false,
                status: EntitlementDecision::STATUS_READ_ONLY,
                reasonCode: 'UNPAID_PAST_GRACE',
                billingState: 'unpaid_past_grace',
                subscriptionState: $effective,
                readOnly: (bool) ($access['unpaid_past_grace_read_only'] ?? true),
            );
        }

        if ($hasOutstanding) {
            return $this->graceDecision($effective);
        }

        return $this->decision(
            allowed: true,
            status: EntitlementDecision::STATUS_ALLOWED,
            reasonCode: 'ALLOWED_ACTIVE_PAID',
            billingState: 'active_paid',
            subscriptionState: $effective,
        );
    }

    private function graceDecision(string $effective): EntitlementDecision
    {
        $access = (array) config('entitlement_governance.access', []);
        $allowsWrites = (bool) ($access['unpaid_within_grace_allows_writes'] ?? true);

        return $this->decision(
            allowed: $allowsWrites,
            status: $allowsWrites ? EntitlementDecision::STATUS_DEGRADED : EntitlementDecision::STATUS_READ_ONLY,
            reasonCode: $allowsWrites ? 'ALLOWED_WITHIN_GRACE' : 'UNPAID_PAST_GRACE',
            billingState: 'unpaid_within_grace',
            subscriptionState: $effective,
            degraded: $allowsWrites,
            readOnly: ! $allowsWrites,
        );
    }

    private function decision(
        bool $allowed,
        string $status,
        string $reasonCode,
        string $billingState,
        ?string $subscriptionState,
        bool $degraded = false,
        bool $readOnly = false,
    ): EntitlementDecision {
        $messages = (array) config('entitlement_governance.reason_codes', []);

        return new EntitlementDecision(
            allowed: $allowed,
            status: $status,
            reasonCode: $reasonCode,
            message: (string) ($messages[$reasonCode] ?? 'Entitlement decision.'),
            planCode: null,
            billingState: $billingState,
            subscriptionState: $subscriptionState,
            degraded: $degraded,
            readOnly: $readOnly,
        );
    }
}
