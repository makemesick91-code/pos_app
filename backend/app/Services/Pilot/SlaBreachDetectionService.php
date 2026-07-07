<?php

namespace App\Services\Pilot;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sprint 17 — SLA breach detection.
 *
 * Computes an SLA due timestamp per severity (config
 * pilot_stabilization.severity_sla_hours) and detects still-open defects whose
 * due timestamp has passed. It is READ-ONLY by default: detect() only reports.
 * Only markBreaches() (invoked by `pilot:sla-check --mark-breached`) mutates —
 * stamping sla_breached_at and appending an SLA_BREACHED event. CI runs the
 * read-only path. No real alerts are ever sent.
 */
class SlaBreachDetectionService
{
    public function __construct(private readonly PilotDefectService $defects) {}

    public function dueAtFor(PilotDefect $defect): ?Carbon
    {
        if ($defect->sla_due_at !== null) {
            return $defect->sla_due_at;
        }

        return $this->defects->computeSlaDueAt($defect->severity, $defect->created_at ?? Carbon::now());
    }

    /**
     * Return the open defects that are past their SLA due timestamp, without
     * mutating anything.
     *
     * @return array{
     *   breached:array<int,array{id:int,defect_reference:string,severity:string,status:string,sla_due_at:?string,already_flagged:bool}>,
     *   count:int
     * }
     */
    public function detect(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $breached = [];

        foreach (PilotDefect::query()->open()->get() as $defect) {
            $dueAt = $this->dueAtFor($defect);
            if ($dueAt === null || $dueAt->greaterThan($now)) {
                continue;
            }

            $breached[] = [
                'id' => $defect->id,
                'defect_reference' => $defect->defect_reference,
                'severity' => $defect->severity,
                'status' => $defect->status,
                'sla_due_at' => optional($dueAt)->toIso8601String(),
                'already_flagged' => $defect->sla_breached_at !== null,
            ];
        }

        return ['breached' => $breached, 'count' => count($breached)];
    }

    /**
     * Persist sla_breached_at and append an SLA_BREACHED event for every overdue
     * open defect not already flagged. Returns the number newly flagged.
     */
    public function markBreaches(?User $actor = null, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $marked = 0;

        foreach (PilotDefect::query()->open()->whereNull('sla_breached_at')->get() as $defect) {
            $dueAt = $this->dueAtFor($defect);
            if ($dueAt === null || $dueAt->greaterThan($now)) {
                continue;
            }

            $defect->sla_breached_at = $now;
            $defect->save();

            $this->defects->appendEvent($defect, PilotDefectEvent::TYPE_SLA_BREACHED, $actor, [
                'message' => "SLA breached for {$defect->severity} defect (due {$dueAt->toIso8601String()}).",
                'payload' => ['sla_due_at' => $dueAt->toIso8601String()],
            ]);

            $marked++;
        }

        return $marked;
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(?Carbon $now = null): array
    {
        $detection = $this->detect($now);
        $bySeverity = [];
        foreach ($detection['breached'] as $item) {
            $bySeverity[$item['severity']] = ($bySeverity[$item['severity']] ?? 0) + 1;
        }

        return [
            'breached_count' => $detection['count'],
            'by_severity' => $bySeverity,
            'breached' => $detection['breached'],
        ];
    }
}
