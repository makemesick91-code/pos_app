<?php

namespace App\Services\TenantOnboarding;

use App\Models\Tenant;
use App\Models\TenantProvisioningRun;
use App\Models\TenantProvisioningStep;
use App\Services\Entitlements\EntitlementAuditService;
use App\Services\Entitlements\EntitlementDecision;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sprint 33 — the single audit writer for onboarding provisioning.
 *
 * Every provisioning mutation and every denied/failed step is written to
 * `tenant_provisioning_steps` (which IS the provisioning audit trail) with
 * REDACTED metadata (ONB-R006/R020/R023). A denied entitlement decision is
 * additionally forwarded to the Sprint 32 EntitlementAuditService so it also
 * lands in `tenant_entitlement_decisions` — onboarding never re-implements the
 * entitlement audit (ONB-R013/R023).
 */
class OnboardingAuditService
{
    public function __construct(
        private readonly OnboardingRedactor $redactor,
        private readonly EntitlementAuditService $entitlementAudit,
    ) {}

    /**
     * Open (or resume) a step row in the running state. Idempotent by
     * run_id + step_key: an existing non-terminal step is reused.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function startStep(
        TenantProvisioningRun $run,
        string $stepKey,
        array $metadata = [],
    ): TenantProvisioningStep {
        $step = TenantProvisioningStep::query()
            ->where('tenant_provisioning_run_id', $run->id)
            ->where('step_key', $stepKey)
            ->first();

        if ($step instanceof TenantProvisioningStep && $step->status === TenantProvisioningStep::STATUS_COMPLETED) {
            return $step;
        }

        if (! $step instanceof TenantProvisioningStep) {
            $step = new TenantProvisioningStep([
                'tenant_provisioning_run_id' => $run->id,
                'step_key' => $stepKey,
                'idempotency_key' => $run->idempotency_key.':'.$stepKey,
            ]);
        }

        $step->tenant_id = $run->tenant_id;
        $step->status = TenantProvisioningStep::STATUS_RUNNING;
        $step->started_at = Carbon::now();
        $step->metadata_json = $this->redactor->redact($metadata);
        $step->save();

        return $step;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function completeStep(
        TenantProvisioningStep $step,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
    ): TenantProvisioningStep {
        $step->status = TenantProvisioningStep::STATUS_COMPLETED;
        $step->reason_code = 'COMPLETED';
        $step->subject_type = $subjectType;
        $step->subject_id = $subjectId;
        $step->completed_at = Carbon::now();
        $step->metadata_json = $this->redactor->redact(array_merge((array) $step->metadata_json, $metadata));
        $step->tenant_id = $step->tenant_id ?? optional($step->run)->tenant_id;
        $step->save();

        return $step;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function skipStep(
        TenantProvisioningStep $step,
        string $reasonCode,
        array $metadata = [],
    ): TenantProvisioningStep {
        $step->status = TenantProvisioningStep::STATUS_SKIPPED;
        $step->reason_code = $reasonCode;
        $step->completed_at = Carbon::now();
        $step->metadata_json = $this->redactor->redact(array_merge((array) $step->metadata_json, $metadata));
        $step->save();

        return $step;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function failStep(
        TenantProvisioningStep $step,
        string $reasonCode,
        string $failureReason,
        array $metadata = [],
    ): TenantProvisioningStep {
        $step->status = TenantProvisioningStep::STATUS_FAILED;
        $step->reason_code = $reasonCode;
        $step->failure_reason = mb_substr($failureReason, 0, 500);
        $step->failed_at = Carbon::now();
        $step->metadata_json = $this->redactor->redact(array_merge((array) $step->metadata_json, $metadata));
        $step->save();

        return $step;
    }

    /**
     * Record an auditable FAILED step by key, creating (or reusing) the step row
     * OUTSIDE the provisioning transaction so it survives a rollback (ONB-R020).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordFailedStep(
        TenantProvisioningRun $run,
        string $stepKey,
        string $reasonCode,
        string $failureReason,
        array $metadata = [],
    ): TenantProvisioningStep {
        $step = TenantProvisioningStep::query()
            ->where('tenant_provisioning_run_id', $run->id)
            ->where('step_key', $stepKey)
            ->first();

        if (! $step instanceof TenantProvisioningStep) {
            $step = new TenantProvisioningStep([
                'tenant_provisioning_run_id' => $run->id,
                'step_key' => $stepKey,
                'idempotency_key' => $run->idempotency_key.':'.$stepKey,
            ]);
        }

        $step->tenant_id = $run->tenant_id;

        return $this->failStep($step, $reasonCode, $failureReason, $metadata);
    }

    /**
     * Record a denied/blocked entitlement decision to the Sprint 32 trail as
     * well (ONB-R013/R023). Never persists a raw decision that shouldn't be.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordEntitlementDenial(
        Tenant $tenant,
        EntitlementDecision $decision,
        ?User $actor,
        string $subjectType,
        array $metadata = [],
    ): void {
        $this->entitlementAudit->record(
            tenant: $tenant,
            decision: $decision,
            actor: $actor,
            subjectType: $subjectType,
            subjectId: null,
            metadata: $this->redactor->redact($metadata),
        );
    }
}
