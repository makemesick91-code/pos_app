<?php

namespace App\Services\Observability;

use App\Models\ObservabilityAnomalyEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sprint 36 — orchestrates the read-only anomaly detectors and (only when
 * executed) persists safe anomaly events (OBS-R003/R013..R019/R029).
 *
 * Dry-run (the default) returns descriptors without persisting. --execute upserts
 * observability_anomaly_events ONLY: a duplicate (same tenant_id + anomaly_key)
 * increments occurrence_count and last_seen_at instead of inserting a new row. No
 * domain state is ever mutated.
 */
class ObservabilityAnomalyScanService
{
    public function __construct(
        private readonly AndroidSyncAnomalyService $sync,
        private readonly BillingPaymentAnomalyService $billing,
        private readonly EntitlementAnomalyService $entitlement,
        private readonly OnboardingAnomalyService $onboarding,
        private readonly ExportReportAnomalyService $exportReport,
        private readonly ObservabilityRedactor $redactor,
        private readonly ObservabilityAuditService $audit,
    ) {}

    /**
     * Acknowledge an open anomaly (audited). Never mutates domain state.
     */
    public function acknowledge(ObservabilityAnomalyEvent $anomaly, User $actor, ?string $reasonCode): ObservabilityAnomalyEvent
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);
        $anomaly->status = ObservabilityAnomalyEvent::STATUS_ACKNOWLEDGED;
        $anomaly->reason_code = $reasonCode;
        $anomaly->acknowledged_by_user_id = $actor->id;
        $anomaly->save();

        $this->audit->record(
            actor: $actor,
            action: 'ANOMALY_ACKNOWLEDGE',
            targetType: ObservabilityAnomalyEvent::class,
            targetId: $anomaly->id,
            tenantId: $anomaly->tenant_id,
            reasonCode: $reasonCode,
            metadata: ['anomaly_key' => $anomaly->anomaly_key, 'severity' => $anomaly->severity],
        );

        return $anomaly;
    }

    /**
     * Resolve (or ignore) an anomaly (audited). Never mutates domain state.
     */
    public function resolve(ObservabilityAnomalyEvent $anomaly, User $actor, ?string $reasonCode, bool $ignore = false): ObservabilityAnomalyEvent
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);
        $anomaly->status = $ignore ? ObservabilityAnomalyEvent::STATUS_IGNORED : ObservabilityAnomalyEvent::STATUS_RESOLVED;
        $anomaly->reason_code = $reasonCode;
        $anomaly->resolved_by_user_id = $actor->id;
        $anomaly->save();

        $this->audit->record(
            actor: $actor,
            action: $ignore ? 'ANOMALY_IGNORE' : 'ANOMALY_RESOLVE',
            targetType: ObservabilityAnomalyEvent::class,
            targetId: $anomaly->id,
            tenantId: $anomaly->tenant_id,
            reasonCode: $reasonCode,
            metadata: ['anomaly_key' => $anomaly->anomaly_key, 'severity' => $anomaly->severity],
        );

        return $anomaly;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function detectAll(?int $tenantId = null): array
    {
        return array_merge(
            $this->sync->detect($tenantId),
            $this->billing->detect($tenantId),
            $this->entitlement->detect($tenantId),
            $this->onboarding->detect($tenantId),
            $this->exportReport->detect($tenantId),
        );
    }

    /**
     * Run a scan. When $execute is false (default) nothing is persisted.
     *
     * @return array<string, mixed>
     */
    public function scan(bool $execute = false, ?int $tenantId = null): array
    {
        $descriptors = $this->detectAll($tenantId);

        $persisted = 0;
        $updated = 0;

        if ($execute) {
            foreach ($descriptors as $descriptor) {
                [$isNew] = $this->upsert($descriptor);
                $isNew ? $persisted++ : $updated++;
            }
        }

        return [
            'executed' => $execute,
            'detected' => count($descriptors),
            'persisted' => $persisted,
            'updated' => $updated,
            'anomalies' => array_map(fn (array $d) => [
                'tenant_id' => $d['tenant_id'] ?? null,
                'anomaly_key' => $d['anomaly_key'],
                'category' => $d['category'],
                'severity' => $d['severity'],
                'reason_code' => $d['reason_code'],
                'summary_safe' => $d['summary_safe'] ?? null,
            ], $descriptors),
        ];
    }

    /**
     * Upsert one anomaly. Dedupes by (tenant_id, anomaly_key). Returns [isNew].
     *
     * @param  array<string, mixed>  $descriptor
     * @return array{0: bool}
     */
    public function upsert(array $descriptor): array
    {
        $tenantId = $descriptor['tenant_id'] ?? null;
        $key = (string) $descriptor['anomaly_key'];
        $now = Carbon::now();

        $existing = ObservabilityAnomalyEvent::query()
            ->where('anomaly_key', $key)
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'))
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('status', [
                ObservabilityAnomalyEvent::STATUS_OPEN,
                ObservabilityAnomalyEvent::STATUS_ACKNOWLEDGED,
                ObservabilityAnomalyEvent::STATUS_SUGGESTED,
            ])
            ->first();

        $metadata = $this->redactor->redact((array) ($descriptor['metadata'] ?? []));
        $summary = $this->redactor->redactText($descriptor['summary_safe'] ?? null);

        if ($existing !== null) {
            $existing->occurrence_count = (int) $existing->occurrence_count + 1;
            $existing->last_seen_at = $now;
            $existing->severity = (string) $descriptor['severity'];
            $existing->summary_safe = $summary;
            $existing->metadata_json = $metadata;
            $existing->save();

            return [false];
        }

        ObservabilityAnomalyEvent::query()->create([
            'tenant_id' => $tenantId,
            'anomaly_key' => $key,
            'category' => (string) $descriptor['category'],
            'severity' => (string) $descriptor['severity'],
            'status' => ObservabilityAnomalyEvent::STATUS_OPEN,
            'reason_code' => $descriptor['reason_code'] ?? null,
            'related_subject_type' => $descriptor['related_subject_type'] ?? null,
            'related_subject_id' => $descriptor['related_subject_id'] ?? null,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'occurrence_count' => 1,
            'summary_safe' => $summary,
            'metadata_json' => $metadata,
        ]);

        return [true];
    }
}
