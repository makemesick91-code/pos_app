<?php

namespace App\Services\Commercial;

use App\Models\CommercialLaunchRisk;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 20 — commercial launch risk lifecycle.
 *
 * Owns create/update/accept-risk/close for commercial launch risks and summarizes
 * risks by severity/status/area into a GO/WATCH/NO_GO decision. Open CRITICAL/HIGH
 * without a valid accepted risk = NO_GO; open MEDIUM without mitigation = WATCH.
 * Accepted risk for CRITICAL/HIGH/MEDIUM requires an approver, a reason, and an
 * expiry. Secret-looking values are stripped from free-text/metadata so the
 * register can never leak credentials. This service never bills, never deploys,
 * and never sends real alerts.
 */
class CommercialRiskGovernanceService
{
    use SanitizesCommercialText;

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): CommercialLaunchRisk
    {
        return CommercialLaunchRisk::query()->create([
            'risk_reference' => (string) ($attributes['risk_reference'] ?? $this->generateReference()),
            'commercial_launch_run_id' => $attributes['commercial_launch_run_id'] ?? null,
            'area' => $this->normalizeArea((string) ($attributes['area'] ?? CommercialLaunchRisk::AREA_OTHER)),
            'severity' => $this->normalizeSeverity((string) ($attributes['severity'] ?? '')),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? CommercialLaunchRisk::STATUS_OPEN)),
            'title' => $this->sanitizeString((string) ($attributes['title'] ?? 'Untitled risk')),
            'description' => $this->sanitizeNullableString($attributes['description'] ?? null),
            'owner_user_id' => $attributes['owner_user_id'] ?? $actor?->id,
            'mitigation' => $this->sanitizeNullableString($attributes['mitigation'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(CommercialLaunchRisk $risk, array $attributes, ?User $actor = null): CommercialLaunchRisk
    {
        $map = [
            'area' => fn ($v) => $this->normalizeArea((string) $v),
            'severity' => fn ($v) => $this->normalizeSeverity((string) $v),
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'title' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $this->sanitizeNullableString($v),
            'owner_user_id' => fn ($v) => $v,
            'mitigation' => fn ($v) => $this->sanitizeNullableString($v),
            'evidence_reference' => fn ($v) => $v,
            'commercial_launch_run_id' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $risk->{$key} = $caster($attributes[$key]);
            }
        }

        $risk->save();

        return $risk->refresh();
    }

    /**
     * Accept a blocking risk as a known risk. For CRITICAL/HIGH/MEDIUM an
     * approver, a reason, and an expiry are required. The severity is preserved.
     *
     * @param array<string,mixed> $data
     */
    public function acceptRisk(CommercialLaunchRisk $risk, array $data, ?User $actor = null): CommercialLaunchRisk
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Accepted risk requires a reason.');
        }

        $requires = in_array($risk->severity, (array) config('commercial_launch.accepted_risk_requires_expiry_for', []), true);
        $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
        $approver = $data['approver'] ?? $data['approver_id'] ?? $actor?->id;

        if ($requires && $expiresAt === null) {
            throw new InvalidArgumentException("Accepted risk for {$risk->severity} requires an expiry/review date.");
        }

        if ($requires && $approver === null) {
            throw new InvalidArgumentException("Accepted risk for {$risk->severity} requires an approver.");
        }

        $risk->status = CommercialLaunchRisk::STATUS_ACCEPTED_RISK;
        $risk->accepted_risk_at = Carbon::now();
        $risk->accepted_risk_by = $approver;
        $risk->accepted_risk_reason = $this->sanitizeString($reason);
        $risk->accepted_risk_expires_at = $expiresAt;
        if (isset($data['evidence_reference'])) {
            $risk->evidence_reference = $data['evidence_reference'];
        }
        $risk->save();

        return $risk->refresh();
    }

    public function close(CommercialLaunchRisk $risk, ?User $actor = null): CommercialLaunchRisk
    {
        $risk->status = CommercialLaunchRisk::STATUS_CLOSED;
        $risk->save();

        return $risk->refresh();
    }

    /**
     * Aggregate risks by severity/status/area and derive a GO/WATCH/NO_GO
     * decision.
     *
     * @return array<string,mixed>
     */
    public function summary(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $blocking = (array) config('commercial_launch.blocking_risk_severities', []);
        $watch = (array) config('commercial_launch.watch_risk_severities', []);

        $open = CommercialLaunchRisk::query()->open()->get();

        $openBySeverity = [];
        foreach (CommercialLaunchRisk::SEVERITIES as $severity) {
            $openBySeverity[$severity] = $open->where('severity', $severity)->count();
        }

        // Open blocking risks are unaccepted by definition (accepted risks leave
        // the open set). Open MEDIUM without mitigation forces WATCH.
        $openBlockingUnaccepted = $open
            ->filter(fn (CommercialLaunchRisk $r) => in_array($r->severity, $blocking, true))
            ->count();

        $openWatchNoMitigation = $open
            ->filter(fn (CommercialLaunchRisk $r) => in_array($r->severity, $watch, true) && trim((string) $r->mitigation) === '')
            ->count();

        $openWatchMitigated = $open
            ->filter(fn (CommercialLaunchRisk $r) => in_array($r->severity, $watch, true) && trim((string) $r->mitigation) !== '')
            ->count();

        $expiredAcceptedRiskBlocking = CommercialLaunchRisk::query()
            ->where('status', CommercialLaunchRisk::STATUS_ACCEPTED_RISK)
            ->whereIn('severity', $blocking)
            ->whereNotNull('accepted_risk_expires_at')
            ->where('accepted_risk_expires_at', '<', $now)
            ->count();

        $expiredAcceptedRiskWatch = CommercialLaunchRisk::query()
            ->where('status', CommercialLaunchRisk::STATUS_ACCEPTED_RISK)
            ->whereIn('severity', $watch)
            ->whereNotNull('accepted_risk_expires_at')
            ->where('accepted_risk_expires_at', '<', $now)
            ->count();

        $decision = self::DECISION_GO;
        if ($openBlockingUnaccepted > 0 || $expiredAcceptedRiskBlocking > 0) {
            $decision = self::DECISION_NO_GO;
        } elseif ($openWatchNoMitigation > 0 || $openWatchMitigated > 0 || $expiredAcceptedRiskWatch > 0) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'counts' => [
                'open_total' => $open->count(),
                'open_by_severity' => $openBySeverity,
                'open_blocking_unaccepted' => $openBlockingUnaccepted,
                'open_watch_no_mitigation' => $openWatchNoMitigation,
                'open_watch_mitigated' => $openWatchMitigated,
                'expired_accepted_risk_blocking' => $expiredAcceptedRiskBlocking,
                'expired_accepted_risk_watch' => $expiredAcceptedRiskWatch,
            ],
        ];
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = strtoupper(trim($severity));
        if (! in_array($severity, CommercialLaunchRisk::SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid severity: {$severity}");
        }

        return $severity;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, CommercialLaunchRisk::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function normalizeArea(string $area): string
    {
        $area = strtoupper(trim($area));

        return in_array($area, CommercialLaunchRisk::AREAS, true) ? $area : CommercialLaunchRisk::AREA_OTHER;
    }

    private function generateReference(): string
    {
        return 'CRISK-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
