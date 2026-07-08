<?php

namespace App\Services\AndroidRuntime;

use App\Models\Tenant;
use App\Models\User;

/**
 * Sprint 34 — exposes the SAFE offline/runtime policy to Android (ADR-R019/R025).
 *
 * The policy tells the client how large/old its offline queue may grow, which
 * actions it may enqueue offline, and the current runtime write posture (allowed /
 * degraded / read_only / blocked) derived from the canonical runtime gate. When
 * the client's entitlement snapshot is stale it must fail safe to read-only
 * (ADR-R025). Output carries no secrets or PII (ADR-R020/R022).
 */
class AndroidOfflinePolicyService
{
    public function __construct(
        private readonly AndroidRuntimeAccessService $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function policyFor(Tenant $tenant, ?User $actor = null): array
    {
        $decision = $this->access->authorizeWrite($tenant, $actor, 'offline_policy');

        return [
            'offline' => [
                'mode_allowed' => (bool) config('android_runtime_governance.offline.mode_allowed', true),
                'queue_max_items' => (int) config('android_runtime_governance.offline.queue_max_items', 500),
                'queue_max_age_hours' => (int) config('android_runtime_governance.offline.queue_max_age_hours', 72),
                'require_client_uuid' => (bool) config('android_runtime_governance.offline.require_client_uuid', true),
                'allowed_actions' => (array) config('android_runtime_governance.offline.allowed_actions', []),
            ],
            'sync' => [
                'batch_idempotency_required' => (bool) config('android_runtime_governance.sync.batch_idempotency_required', true),
                'max_items_per_batch' => (int) config('android_runtime_governance.sync.max_items_per_batch', 200),
                'require_item_client_id' => (bool) config('android_runtime_governance.sync.require_item_client_id', true),
            ],
            'runtime' => [
                'status' => $decision->status,
                'write_allowed' => $decision->allowed,
                'read_only' => $decision->readOnly() || (! $decision->allowed),
                'reason_code' => $decision->reasonCode,
                'billing_state' => $decision->billingState,
            ],
            // ADR-R025 — the client applies this when its snapshot is stale.
            'stale_entitlement_behavior' => (string) config('android_runtime_governance.runtime_behavior.stale_entitlement', 'read_only'),
            'conflict_policy' => (string) config('android_runtime_governance.conflict_policy', 'server_authoritative_deterministic'),
        ];
    }
}
