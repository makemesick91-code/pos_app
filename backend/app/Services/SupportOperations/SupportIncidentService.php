<?php

namespace App\Services\SupportOperations;

use App\Models\Tenant;
use App\Models\TenantSupportAction;
use App\Models\TenantSupportIncident;
use App\Models\TenantSupportIncidentNote;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sprint 35 — create/update/resolve/close support incidents and add redacted
 * notes (SUP-R023/R024). Every incident and note is tenant-isolated; every
 * mutation requires a reason code and is audited. Titles, summaries and note
 * bodies are redacted before persistence.
 */
class SupportIncidentService
{
    public function __construct(
        private readonly SupportRedactor $redactor,
        private readonly SupportAuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Tenant $tenant, User $actor, array $data): TenantSupportIncident
    {
        $reasonCode = $this->audit->assertReasonCode($data['reason_code'] ?? null);

        $incident = DB::transaction(function () use ($tenant, $actor, $data, $reasonCode) {
            return TenantSupportIncident::query()->create([
                'tenant_id' => $tenant->id,
                'opened_by_user_id' => $actor->id,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'incident_number' => $this->generateNumber(),
                'category' => $data['category'],
                'severity' => $data['severity'],
                'status' => TenantSupportIncident::STATUS_OPEN,
                'title_safe' => $this->redactor->redactText($data['title'], 200),
                'summary_safe' => $this->redactor->redactText($data['summary'] ?? null),
                'primary_reason_code' => $reasonCode,
                'related_subject_type' => $data['related_subject_type'] ?? null,
                'related_subject_id' => $data['related_subject_id'] ?? null,
                'opened_at' => Carbon::now(),
                'metadata_json' => $this->redactor->redact((array) ($data['metadata'] ?? [])),
            ]);
        });

        $this->audit->record(
            actor: $actor,
            tenantId: $tenant->id,
            actionKey: 'incident.create',
            actionType: TenantSupportAction::TYPE_INCIDENT_CREATED,
            status: TenantSupportAction::STATUS_COMPLETED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantSupportIncident::class,
            relatedSubjectId: $incident->id,
            metadata: ['category' => $incident->category, 'severity' => $incident->severity],
        );

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TenantSupportIncident $incident, User $actor, array $data): TenantSupportIncident
    {
        $reasonCode = $this->audit->assertReasonCode($data['reason_code'] ?? null);

        DB::transaction(function () use ($incident, $data) {
            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $incident->status = $data['status'];
                if ($data['status'] === TenantSupportIncident::STATUS_RESOLVED && $incident->resolved_at === null) {
                    $incident->resolved_at = Carbon::now();
                }
                if (in_array($data['status'], [TenantSupportIncident::STATUS_CLOSED, TenantSupportIncident::STATUS_CANCELLED], true) && $incident->closed_at === null) {
                    $incident->closed_at = Carbon::now();
                }
            }
            if (array_key_exists('severity', $data) && $data['severity'] !== null) {
                $incident->severity = $data['severity'];
            }
            if (array_key_exists('assigned_to_user_id', $data)) {
                $incident->assigned_to_user_id = $data['assigned_to_user_id'];
            }
            if (array_key_exists('summary', $data) && $data['summary'] !== null) {
                $incident->summary_safe = $this->redactor->redactText($data['summary']);
            }
            $incident->save();
        });

        $this->audit->record(
            actor: $actor,
            tenantId: $incident->tenant_id,
            actionKey: 'incident.update',
            actionType: TenantSupportAction::TYPE_INCIDENT_UPDATED,
            status: TenantSupportAction::STATUS_COMPLETED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantSupportIncident::class,
            relatedSubjectId: $incident->id,
            metadata: ['status' => $incident->status, 'severity' => $incident->severity],
        );

        return $incident->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addNote(TenantSupportIncident $incident, User $actor, array $data): TenantSupportIncidentNote
    {
        $reasonCode = $this->audit->assertReasonCode($data['reason_code'] ?? null);

        $note = DB::transaction(function () use ($incident, $actor, $data) {
            return TenantSupportIncidentNote::query()->create([
                'tenant_support_incident_id' => $incident->id,
                'tenant_id' => $incident->tenant_id,
                'author_user_id' => $actor->id,
                'note_type' => $data['note_type'] ?? TenantSupportIncidentNote::TYPE_INTERNAL,
                'body_safe' => $this->redactor->redactText($data['body']),
                'metadata_json' => $this->redactor->redact((array) ($data['metadata'] ?? [])),
            ]);
        });

        $this->audit->record(
            actor: $actor,
            tenantId: $incident->tenant_id,
            actionKey: 'incident.note',
            actionType: TenantSupportAction::TYPE_NOTE_ADDED,
            status: TenantSupportAction::STATUS_COMPLETED,
            reasonCode: $reasonCode,
            relatedSubjectType: TenantSupportIncident::class,
            relatedSubjectId: $incident->id,
            metadata: ['note_type' => $note->note_type],
        );

        return $note;
    }

    private function generateNumber(): string
    {
        return 'SUP-'.Carbon::now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
