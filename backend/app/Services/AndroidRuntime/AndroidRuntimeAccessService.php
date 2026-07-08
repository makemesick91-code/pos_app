<?php

namespace App\Services\AndroidRuntime;

use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use App\Services\Entitlements\EntitlementDecision;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 34 — the central, server-side Android runtime gate (ADR-R001).
 *
 * Every Android runtime write (activation, cashier session, sync) resolves its
 * billing/subscription/lifecycle dimension through the Sprint 32
 * EntitlementAccessService and NEVER re-implements it (ADR-R001/R006). Manual
 * suspension always wins (ADR-R007); unpaid-past-grace and trial-expired fail
 * closed to blocked/read-only per config android_runtime_governance.runtime_behavior
 * (ADR-R008/R009). It additionally enforces the device-activation, register/device
 * consistency and cashier-role dimensions that entitlement does not cover
 * (ADR-R010/R026/R027). Every decision is deterministic and safe to return.
 */
class AndroidRuntimeAccessService
{
    public function __construct(
        private readonly EntitlementAccessService $entitlements,
    ) {}

    /**
     * Billing/lifecycle write gate for a generic Android write.
     */
    public function authorizeWrite(Tenant $tenant, ?User $actor, string $context = 'android_write'): AndroidRuntimeDecision
    {
        return $this->mapEntitlementDecision(
            $this->entitlements->canWrite($tenant, $actor, $context),
        );
    }

    /**
     * Device activation gate: billing/lifecycle write state AND the plan device
     * limit. Fails closed for an unknown tenant/plan (ADR-R005/R006).
     */
    public function authorizeActivation(Tenant $tenant, ?User $actor = null): AndroidRuntimeDecision
    {
        $write = $this->authorizeWrite($tenant, $actor, 'device_activation');
        if ($write->denied()) {
            return $write;
        }

        $limit = $this->entitlements->canRegisterDevice($tenant, $actor);
        if ($limit->denied()) {
            return $this->mapEntitlementDecision($limit);
        }

        return new AndroidRuntimeDecision(
            allowed: true,
            status: AndroidRuntimeDecision::STATUS_ALLOWED,
            reasonCode: 'ACTIVATION_ALLOWED',
            message: 'Device activation is permitted.',
            planCode: $limit->planCode,
            billingState: $write->billingState,
        );
    }

    /**
     * Sync gate: the activation must still be usable (not revoked/expired) and the
     * tenant must be writable. A revoked/expired device is denied and mapped to the
     * deterministic `device_revoked` conflict (ADR-R026).
     */
    public function authorizeSync(Tenant $tenant, TenantDeviceActivation $activation, ?User $cashier = null): AndroidRuntimeDecision
    {
        if ($activation->tenant_id !== $tenant->id) {
            return $this->denied('DEVICE_TENANT_MISMATCH', 'register_mismatch', 'Device does not belong to this tenant.', Response::HTTP_FORBIDDEN);
        }

        if (! $activation->isUsable()) {
            $reason = $activation->isRevoked() ? 'DEVICE_REVOKED' : ($activation->isExpired() ? 'DEVICE_EXPIRED' : 'DEVICE_NOT_ACTIVATED');

            return $this->denied($reason, 'device_revoked', 'The device activation is not usable for sync.', Response::HTTP_FORBIDDEN);
        }

        return $this->authorizeWrite($tenant, $cashier, 'android_sync');
    }

    /**
     * Cashier runtime session gate: role, tenant/branch/register/device consistency
     * and the billing/lifecycle write state (ADR-R010). Returns read_only rather
     * than allowed when the tenant is write-degraded so a cashier can still read.
     */
    public function authorizeCashierSession(
        Tenant $tenant,
        User $cashier,
        ?TenantDeviceActivation $activation = null,
        ?int $storeId = null,
        ?int $registerId = null,
    ): AndroidRuntimeDecision {
        if ((int) $cashier->tenant_id !== (int) $tenant->id) {
            return $this->denied('CASHIER_TENANT_MISMATCH', 'entitlement_denied', 'Cashier does not belong to this tenant.', Response::HTTP_FORBIDDEN);
        }

        $roles = (array) config('android_runtime_governance.cashier.operator_roles', []);
        if (! in_array((string) $cashier->role, $roles, true)) {
            return $this->denied('CASHIER_ROLE_INVALID', 'entitlement_denied', 'User role may not operate a cashier runtime session.', Response::HTTP_FORBIDDEN);
        }

        if ($activation !== null) {
            if ((int) $activation->tenant_id !== (int) $tenant->id) {
                return $this->denied('DEVICE_TENANT_MISMATCH', 'register_mismatch', 'Device does not belong to this tenant.', Response::HTTP_FORBIDDEN);
            }

            if (! $activation->isUsable()) {
                return $this->denied('DEVICE_REVOKED', 'device_revoked', 'The device activation is not usable.', Response::HTTP_FORBIDDEN);
            }

            if ($storeId !== null && $activation->store_id !== null && (int) $activation->store_id !== (int) $storeId) {
                return $this->denied('REGISTER_MISMATCH', 'register_mismatch', 'The store/register does not match the activated device.', Response::HTTP_FORBIDDEN);
            }

            if ($registerId !== null && $activation->register_id !== null && (int) $activation->register_id !== (int) $registerId) {
                return $this->denied('REGISTER_MISMATCH', 'register_mismatch', 'The register does not match the activated device.', Response::HTTP_FORBIDDEN);
            }
        }

        // Role + consistency OK; the write dimension decides allowed vs read_only.
        return $this->authorizeWrite($tenant, $cashier, 'cashier_session');
    }

    // --- internals -----------------------------------------------------------

    private function mapEntitlementDecision(EntitlementDecision $decision): AndroidRuntimeDecision
    {
        if ($decision->allowed) {
            return new AndroidRuntimeDecision(
                allowed: true,
                status: $decision->degraded ? AndroidRuntimeDecision::STATUS_DEGRADED : AndroidRuntimeDecision::STATUS_ALLOWED,
                reasonCode: $decision->reasonCode,
                message: $decision->message,
                planCode: $decision->planCode,
                billingState: $decision->billingState,
            );
        }

        $behaviorKey = match ($decision->reasonCode) {
            'MANUALLY_SUSPENDED' => 'suspended',
            'UNPAID_PAST_GRACE' => 'unpaid_past_grace',
            'TRIAL_EXPIRED' => 'trial_expired',
            default => null,
        };

        $behavior = $behaviorKey !== null
            ? (string) config('android_runtime_governance.runtime_behavior.'.$behaviorKey, 'block')
            : 'block';

        $status = $behavior === 'read_only'
            ? AndroidRuntimeDecision::STATUS_READ_ONLY
            : AndroidRuntimeDecision::STATUS_BLOCKED;

        $conflict = match ($decision->reasonCode) {
            'MANUALLY_SUSPENDED' => 'tenant_suspended',
            'UNPAID_PAST_GRACE' => 'unpaid_past_grace',
            'TRIAL_EXPIRED' => 'trial_expired',
            default => 'entitlement_denied',
        };

        return new AndroidRuntimeDecision(
            allowed: false,
            status: $status,
            reasonCode: $decision->reasonCode,
            message: $decision->message,
            conflictCode: $conflict,
            httpStatus: $this->statusFor($decision->reasonCode),
            planCode: $decision->planCode,
            billingState: $decision->billingState,
        );
    }

    private function denied(string $reasonCode, string $conflictCode, string $message, int $status): AndroidRuntimeDecision
    {
        return new AndroidRuntimeDecision(
            allowed: false,
            status: AndroidRuntimeDecision::STATUS_BLOCKED,
            reasonCode: $reasonCode,
            message: $message,
            conflictCode: $conflictCode,
            httpStatus: $status,
        );
    }

    private function statusFor(string $reasonCode): int
    {
        return match ($reasonCode) {
            'MANUALLY_SUSPENDED' => Response::HTTP_LOCKED,
            'UNPAID_PAST_GRACE', 'TRIAL_EXPIRED', 'MISSING_SUBSCRIPTION', 'SUBSCRIPTION_CANCELLED' => Response::HTTP_PAYMENT_REQUIRED,
            'USAGE_LIMIT_EXCEEDED', 'DEVICE_LIMIT_EXCEEDED' => Response::HTTP_TOO_MANY_REQUESTS,
            default => Response::HTTP_FORBIDDEN,
        };
    }
}
