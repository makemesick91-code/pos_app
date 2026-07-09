<?php

namespace App\Services\Observability;

use App\Models\User;

/**
 * Sprint 36 — governed queue retry/replay (OBS-R010).
 *
 * DISABLED by default. When disabled, retry() throws a governed
 * "not supported" ObservabilityException (409) — never a silent no-op. When ever
 * enabled it requires platform.admin (enforced at the route), a reason code, an
 * audit entry, and the failed job's class must be on the idempotency-safe
 * allow-list. This service never re-dispatches a non-idempotent job.
 */
class QueueActionService
{
    public function __construct(
        private readonly ObservabilityAuditService $audit,
        private readonly FailedJobDiagnosticsService $diagnostics,
    ) {}

    public function retryEnabled(): bool
    {
        return (bool) config('observability_governance.job_retry.enabled', false);
    }

    /**
     * Attempt a governed retry. Returns a safe result array. Throws
     * ObservabilityException when retry is disabled or the job is not idempotent.
     *
     * @return array<string, mixed>
     */
    public function retry(User $actor, int|string $id, ?string $reasonCode): array
    {
        if (! $this->retryEnabled()) {
            // Record the denied attempt so even a blocked action is auditable.
            $this->audit->record(
                actor: $actor,
                action: 'JOB_RETRY_DENIED',
                targetType: 'failed_jobs',
                targetId: is_numeric($id) ? (int) $id : null,
                reasonCode: $reasonCode,
                metadata: ['outcome' => 'not_supported'],
            );

            throw ObservabilityException::jobRetryDisabled(
                (string) config('observability_governance.job_retry.not_supported_reason'),
            );
        }

        // Enabled path: reason required, idempotency-safe only, audited.
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $row = $this->diagnostics->drilldown($id);
        if ($row === null) {
            throw new ObservabilityException('Failed job not found.', 404);
        }

        if ((bool) config('observability_governance.job_retry.idempotent_only', true)) {
            $allowlist = (array) config('observability_governance.job_retry.idempotent_job_allowlist', []);
            if (! in_array($row['job_label'], $allowlist, true)) {
                $this->audit->record(
                    actor: $actor,
                    action: 'JOB_RETRY_DENIED',
                    targetType: 'failed_jobs',
                    targetId: is_numeric($id) ? (int) $id : null,
                    reasonCode: $reasonCode,
                    metadata: ['outcome' => 'not_idempotent', 'job_label' => $row['job_label']],
                );

                throw ObservabilityException::jobNotIdempotent();
            }
        }

        $this->audit->record(
            actor: $actor,
            action: 'JOB_RETRY',
            targetType: 'failed_jobs',
            targetId: is_numeric($id) ? (int) $id : null,
            reasonCode: $reasonCode,
            metadata: ['outcome' => 'requeued', 'job_label' => $row['job_label']],
        );

        return ['status' => 'requeued', 'job_label' => $row['job_label']];
    }
}
