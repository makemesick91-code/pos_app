<?php

namespace App\Services\Entitlements;

/**
 * Sprint 32 — the immutable, deterministic result of a runtime entitlement
 * decision (ENT-R019).
 *
 * Produced only by EntitlementAccessService (via EntitlementBillingStateService /
 * EntitlementUsageService). Carries a stable reason code, a UI/API-safe message,
 * and the explaining context (plan, usage, limit, billing/subscription state).
 * It never carries secrets or raw PII; metadata is already redacted upstream.
 */
final class EntitlementDecision
{
    public const STATUS_ALLOWED = 'allowed';

    public const STATUS_DENIED = 'denied';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_READ_ONLY = 'read_only';

    public const STATUS_BYPASSED = 'bypassed';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $status,
        public readonly string $reasonCode,
        public readonly string $message,
        public readonly ?string $entitlementKey = null,
        public readonly ?string $resourceType = null,
        public readonly ?string $action = null,
        public readonly ?string $planCode = null,
        public readonly ?int $currentUsage = null,
        public readonly ?int $limitValue = null,
        public readonly ?string $billingState = null,
        public readonly ?string $subscriptionState = null,
        public readonly bool $degraded = false,
        public readonly bool $readOnly = false,
        public readonly array $metadata = [],
    ) {}

    public function denied(): bool
    {
        return ! $this->allowed;
    }

    /**
     * Whether this decision should be persisted to the audit trail (ENT-R018).
     */
    public function shouldPersist(): bool
    {
        return in_array($this->status, (array) config('entitlement_governance.persist_decisions', []), true);
    }

    /**
     * A copy of this decision re-tagged with a resource/action/entitlement key
     * context (used when a billing-state decision is applied to a concrete
     * resource creation without recomputing the billing state).
     */
    public function withContext(
        ?string $entitlementKey,
        ?string $resourceType,
        ?string $action,
    ): self {
        return new self(
            allowed: $this->allowed,
            status: $this->status,
            reasonCode: $this->reasonCode,
            message: $this->message,
            entitlementKey: $entitlementKey ?? $this->entitlementKey,
            resourceType: $resourceType ?? $this->resourceType,
            action: $action ?? $this->action,
            planCode: $this->planCode,
            currentUsage: $this->currentUsage,
            limitValue: $this->limitValue,
            billingState: $this->billingState,
            subscriptionState: $this->subscriptionState,
            degraded: $this->degraded,
            readOnly: $this->readOnly,
            metadata: $this->metadata,
        );
    }

    /**
     * Safe, redacted representation for API/CLI/admin output (ENT-R020).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'decision' => $this->status,
            'reason_code' => $this->reasonCode,
            'message' => $this->message,
            'entitlement_key' => $this->entitlementKey,
            'resource_type' => $this->resourceType,
            'action' => $this->action,
            'plan_code' => $this->planCode,
            'current_usage' => $this->currentUsage,
            'limit_value' => $this->limitValue,
            'billing_state' => $this->billingState,
            'subscription_state' => $this->subscriptionState,
            'degraded' => $this->degraded,
            'read_only' => $this->readOnly,
        ];
    }
}
