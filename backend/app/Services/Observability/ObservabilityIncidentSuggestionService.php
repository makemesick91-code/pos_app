<?php

namespace App\Services\Observability;

use App\Models\ObservabilityAlertSuggestion;
use App\Models\ObservabilityAnomalyEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SupportOperations\SupportIncidentService;

/**
 * Sprint 36 — alert / incident suggestion (OBS-R018/R021).
 *
 * A scan-driven generator creates SUGGESTIONS only from anomaly events; it never
 * auto-creates a support incident and never mutates tenant state. Accepting a
 * suggestion may create a Sprint 35 support incident, but ONLY through the
 * governed SupportIncidentService (which itself audits + redacts and never
 * mutates billing/entitlement/device state). Dismiss is audited.
 */
class ObservabilityIncidentSuggestionService
{
    private const SEVERITY_RANK = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];

    public function __construct(
        private readonly ObservabilityAuditService $audit,
        private readonly ObservabilityRedactor $redactor,
        private readonly SupportIncidentService $incidents,
    ) {}

    /**
     * Generate suggestions from open anomaly events at/above the configured
     * minimum severity. Idempotent: an anomaly that already has an open
     * suggestion is skipped. Persists suggestions only.
     *
     * @return array<string, mixed>
     */
    public function generateFromAnomalies(?User $actor = null): array
    {
        if (! (bool) config('observability_governance.incident_suggestion.enabled', true)) {
            return ['created' => 0, 'skipped' => 0, 'suggestions' => []];
        }

        $minSeverity = (string) config('observability_governance.incident_suggestion.min_severity_for_suggestion', 'medium');
        $minRank = self::SEVERITY_RANK[$minSeverity] ?? 1;

        $created = 0;
        $skipped = 0;
        $out = [];

        $anomalies = ObservabilityAnomalyEvent::query()->open()->orderByDesc('id')->get();
        foreach ($anomalies as $anomaly) {
            if ((self::SEVERITY_RANK[$anomaly->severity] ?? 0) < $minRank) {
                continue;
            }
            $exists = ObservabilityAlertSuggestion::query()
                ->where('anomaly_event_id', $anomaly->id)
                ->whereIn('status', [ObservabilityAlertSuggestion::STATUS_SUGGESTED, ObservabilityAlertSuggestion::STATUS_LINKED_TO_INCIDENT])
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            $suggestion = ObservabilityAlertSuggestion::query()->create([
                'tenant_id' => $anomaly->tenant_id,
                'anomaly_event_id' => $anomaly->id,
                'suggested_action' => 'review_'.$anomaly->category.'_anomaly',
                'severity' => $anomaly->severity,
                'status' => ObservabilityAlertSuggestion::STATUS_SUGGESTED,
                'reason_code' => 'incident_opened',
                'summary_safe' => $this->redactor->redactText($anomaly->summary_safe),
                'metadata_json' => $this->redactor->redact(['anomaly_key' => $anomaly->anomaly_key, 'category' => $anomaly->category]),
                'created_by_user_id' => $actor?->id,
            ]);

            // Mark the anomaly as having a suggestion (still open, not mutated).
            $anomaly->status = ObservabilityAnomalyEvent::STATUS_SUGGESTED;
            $anomaly->save();

            $created++;
            $out[] = $suggestion->toSafeArray();
        }

        return ['created' => $created, 'skipped' => $skipped, 'suggestions' => $out];
    }

    /**
     * Dismiss a suggestion (audited). Never mutates tenant state.
     */
    public function dismiss(ObservabilityAlertSuggestion $suggestion, User $actor, ?string $reasonCode): ObservabilityAlertSuggestion
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $suggestion->status = ObservabilityAlertSuggestion::STATUS_DISMISSED;
        $suggestion->reason_code = $reasonCode;
        $suggestion->resolved_by_user_id = $actor->id;
        $suggestion->save();

        $this->audit->record(
            actor: $actor,
            action: 'ALERT_SUGGESTION_DISMISS',
            targetType: ObservabilityAlertSuggestion::class,
            targetId: $suggestion->id,
            tenantId: $suggestion->tenant_id,
            reasonCode: $reasonCode,
            metadata: ['severity' => $suggestion->severity],
        );

        return $suggestion;
    }

    /**
     * Accept a suggestion. When accept-create-incident is allowed and the
     * suggestion is tenant-scoped, create a Sprint 35 support incident through
     * SupportIncidentService (audited, redacted) and link it. Otherwise mark
     * accepted only. Never mutates any other tenant state.
     */
    public function accept(ObservabilityAlertSuggestion $suggestion, User $actor, ?string $reasonCode): ObservabilityAlertSuggestion
    {
        $reasonCode = $this->audit->assertReasonCode($reasonCode);

        $allowCreate = (bool) config('observability_governance.incident_suggestion.allow_accept_create_incident', true);
        $incidentId = null;

        if ($allowCreate && $suggestion->tenant_id !== null) {
            $tenant = Tenant::query()->find($suggestion->tenant_id);
            if ($tenant !== null) {
                $categoryMap = (array) config('observability_governance.incident_suggestion.category_map', []);
                $anomaly = $suggestion->anomalyEvent()->first();
                $anomalyCategory = $anomaly?->category ?? 'other';
                $supportCategory = (string) ($categoryMap[$anomalyCategory] ?? 'other');
                $supportReason = (string) config('observability_governance.incident_suggestion.default_incident_reason_code', 'internal_review');

                $incident = $this->incidents->create($tenant, $actor, [
                    'reason_code' => $supportReason,
                    'category' => $supportCategory,
                    'severity' => $suggestion->severity,
                    'title' => 'Observability anomaly: '.$suggestion->suggested_action,
                    'summary' => $suggestion->summary_safe ?? 'Auto-suggested from observability anomaly detection.',
                    'metadata' => ['anomaly_event_id' => $suggestion->anomaly_event_id],
                ]);
                $incidentId = $incident->id;

                if ($anomaly !== null) {
                    $anomaly->status = ObservabilityAnomalyEvent::STATUS_LINKED_TO_INCIDENT;
                    $anomaly->save();
                }
            }
        }

        $suggestion->status = $incidentId !== null
            ? ObservabilityAlertSuggestion::STATUS_LINKED_TO_INCIDENT
            : ObservabilityAlertSuggestion::STATUS_ACCEPTED;
        $suggestion->support_incident_id = $incidentId;
        $suggestion->reason_code = $reasonCode;
        $suggestion->resolved_by_user_id = $actor->id;
        $suggestion->save();

        $this->audit->record(
            actor: $actor,
            action: 'ALERT_SUGGESTION_ACCEPT',
            targetType: ObservabilityAlertSuggestion::class,
            targetId: $suggestion->id,
            tenantId: $suggestion->tenant_id,
            reasonCode: $reasonCode,
            metadata: ['support_incident_id' => $incidentId, 'severity' => $suggestion->severity],
        );

        return $suggestion;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $status = ObservabilityAlertSuggestion::STATUS_SUGGESTED, int $limit = 100): array
    {
        return ObservabilityAlertSuggestion::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->map(fn (ObservabilityAlertSuggestion $s) => $s->toSafeArray())
            ->all();
    }
}
