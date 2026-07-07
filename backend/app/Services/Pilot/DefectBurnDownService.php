<?php

namespace App\Services\Pilot;

use App\Models\PilotDefect;
use Illuminate\Support\Carbon;

/**
 * Sprint 17 — defect burn-down aggregation + gating decision.
 *
 * Counts defects by severity, status, and area; counts open blocking defects,
 * SLA-breached defects, accepted-risk defects, and fix-verification states; and
 * produces a GO / WATCH / NO-GO decision:
 *
 *   NO-GO  — an open BLOCKER/CRITICAL without valid accepted risk, OR an
 *            accepted-risk BLOCKER/CRITICAL whose acceptance has expired, OR (when
 *            config disables the downgrade) any accepted blocking defect.
 *   WATCH  — an open MAJOR defect, OR a validly accepted blocking/major defect.
 *   GO     — only MINOR/TRIVIAL (allowed) severities remain open, or nothing open.
 *
 * Accepted risk changes the decision impact but never the recorded severity.
 */
class DefectBurnDownService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO-GO';

    public function __construct(private readonly SlaBreachDetectionService $sla) {}

    /**
     * @return array<string,mixed>
     */
    public function summary(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $defects = PilotDefect::query()->get();

        $bySeverity = $this->countBy($defects, 'severity');
        $byStatus = $this->countBy($defects, 'status');
        $byArea = $this->countBy($defects, 'area');

        $blockingSeverities = (array) config('pilot_stabilization.blocking_severities', []);
        $watchSeverities = (array) config('pilot_stabilization.watch_severities', []);

        $openBlocking = 0;
        $openMajor = 0;
        $acceptedBlockingValid = 0;
        $acceptedBlockingExpired = 0;
        $slaBreachedOpen = 0;

        foreach ($defects as $defect) {
            if ($defect->isOpen()) {
                if (in_array($defect->severity, $blockingSeverities, true)) {
                    $openBlocking++;
                }
                if (in_array($defect->severity, $watchSeverities, true)) {
                    $openMajor++;
                }
                if ($this->isSlaBreached($defect, $now)) {
                    $slaBreachedOpen++;
                }
            }

            if ($defect->status === PilotDefect::STATUS_ACCEPTED_RISK
                && in_array($defect->severity, $blockingSeverities, true)) {
                $defect->hasValidAcceptedRisk() ? $acceptedBlockingValid++ : $acceptedBlockingExpired++;
            }
        }

        $counts = [
            'total' => $defects->count(),
            'open' => $defects->filter(fn (PilotDefect $d) => $d->isOpen())->count(),
            'open_blocking' => $openBlocking,
            'open_major' => $openMajor,
            'sla_breached_open' => $slaBreachedOpen,
            'accepted_risk' => $byStatus[PilotDefect::STATUS_ACCEPTED_RISK] ?? 0,
            'accepted_risk_blocking_valid' => $acceptedBlockingValid,
            'accepted_risk_blocking_expired' => $acceptedBlockingExpired,
            'fixed' => $byStatus[PilotDefect::STATUS_FIXED] ?? 0,
            'retest' => $byStatus[PilotDefect::STATUS_RETEST] ?? 0,
            'verified' => $byStatus[PilotDefect::STATUS_VERIFIED] ?? 0,
            'closed' => $byStatus[PilotDefect::STATUS_CLOSED] ?? 0,
        ];

        $decision = $this->decide($counts);

        return [
            'decision' => $decision,
            'counts' => $counts,
            'by_severity' => $bySeverity,
            'by_status' => $byStatus,
            'by_area' => $byArea,
        ];
    }

    /**
     * @param array<string,int> $counts
     */
    public function decide(array $counts): string
    {
        $downgrade = (bool) config('pilot_stabilization.accepted_risk_downgrades_blocking_to_watch', true);

        if (($counts['open_blocking'] ?? 0) > 0) {
            return self::DECISION_NO_GO;
        }

        if (($counts['accepted_risk_blocking_expired'] ?? 0) > 0) {
            return self::DECISION_NO_GO;
        }

        if (($counts['accepted_risk_blocking_valid'] ?? 0) > 0) {
            return $downgrade ? self::DECISION_WATCH : self::DECISION_NO_GO;
        }

        if (($counts['open_major'] ?? 0) > 0) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    private function isSlaBreached(PilotDefect $defect, Carbon $now): bool
    {
        if ($defect->sla_breached_at !== null) {
            return true;
        }

        $dueAt = $this->sla->dueAtFor($defect);

        return $dueAt !== null && $dueAt->lessThanOrEqualTo($now);
    }

    /**
     * @param \Illuminate\Support\Collection<int,PilotDefect> $defects
     * @return array<string,int>
     */
    private function countBy($defects, string $attribute): array
    {
        $out = [];
        foreach ($defects as $defect) {
            $key = (string) $defect->getAttribute($attribute);
            $out[$key] = ($out[$key] ?? 0) + 1;
        }

        return $out;
    }
}
