<?php

namespace App\Services\AndroidRuntime;

use App\Models\Tenant;
use App\Models\TenantDeviceActivation;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;

/**
 * Sprint 34 — validates and summarises a cashier runtime session (ADR-R010/R011).
 *
 * There is no separate cashier-session table in this codebase; the "session" is a
 * validated runtime posture: the cashier's role, tenant/branch/register/device
 * consistency and the tenant's billing/lifecycle write state, resolved through the
 * canonical runtime gate. A denied login/session/write attempt is audit-logged with
 * redacted metadata (ADR-R011). The summary is safe to return to the client.
 */
class CashierRuntimeSessionService
{
    public function __construct(
        private readonly AndroidRuntimeAccessService $access,
        private readonly AdminAuditLogger $adminAudit,
        private readonly AndroidSyncRedactor $redactor,
    ) {}

    public function check(
        Tenant $tenant,
        User $cashier,
        ?TenantDeviceActivation $activation = null,
        ?int $storeId = null,
        ?int $registerId = null,
    ): AndroidRuntimeDecision {
        $decision = $this->access->authorizeCashierSession($tenant, $cashier, $activation, $storeId, $registerId);

        if ($decision->denied()) {
            // ADR-R011 — audit denied/degraded cashier runtime decisions.
            $this->adminAudit->log(
                actor: $cashier,
                action: AndroidRuntimeAuditService::ACTION_CASHIER_DENIED,
                targetType: 'android_cashier_session',
                targetId: $cashier->id,
                tenantId: $tenant->id,
                metadata: $this->redactor->redact([
                    'reason_code' => $decision->reasonCode,
                    'status' => $decision->status,
                    'store_id' => $storeId,
                    'register_id' => $registerId,
                ]),
            );
        }

        return $decision;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant, User $cashier, AndroidRuntimeDecision $decision): array
    {
        return [
            'tenant_id' => $tenant->id,
            'cashier_user_id' => $cashier->id,
            'role' => $cashier->role,
            'session_timeout_minutes' => (int) config('android_runtime_governance.cashier.session_timeout_minutes', 720),
            'runtime' => $decision->toArray(),
        ];
    }
}
