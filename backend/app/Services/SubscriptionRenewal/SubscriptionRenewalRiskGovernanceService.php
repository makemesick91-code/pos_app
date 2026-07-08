<?php

namespace App\Services\SubscriptionRenewal;

use App\Models\SubscriptionRenewalRisk;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 24 — subscription renewal risk lifecycle.
 *
 * Owns create/update/accept-risk/close for subscription renewal risks and
 * summarizes risks by severity/status/area into a GO/WATCH/NO_GO decision. Open
 * CRITICAL/HIGH without a valid accepted risk = NO_GO; open MEDIUM without
 * mitigation = WATCH. Accepted risk for CRITICAL/HIGH/MEDIUM requires an approver,
 * a reason, and an expiry; an expired accepted risk re-blocks. Secret-looking
 * values are stripped from free-text/metadata.
 */
class SubscriptionRenewalRiskGovernanceService
{
    use SanitizesSubscriptionRenewalText;

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SubscriptionRenewalRisk
    {
        return SubscriptionRenewalRisk::query()->create([
            'risk_reference' => (string) ($attributes['risk_reference'] ?? $this->generateReference()),
            'candidate_id' => $attributes['candidate_id'] ?? null,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'tenant_subscription_id' => $attributes['tenant_subscription_id'] ?? null,
            'area' => $this->normalizeArea((string) ($attributes['area'] ?? SubscriptionRenewalRisk::AREA_OTHER)),
            'severity' => $this->normalizeSeverity((string) ($attributes['severity'] ?? '')),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SubscriptionRenewalRisk::STATUS_OPEN)),
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
    public function update(SubscriptionRenewalRisk $risk, array $attributes, ?User $actor = null): SubscriptionRenewalRisk
    {
        $map = [
            'candidate_id' => fn ($v) => $v,
            'tenant_id' => fn ($v) => $v,
            'tenant_subscription_id' => fn ($v) => $v,
            'area' => fn ($v) => $this->normalizeArea((string) $v),
            'severity' => fn ($v) => $this->normalizeSeverity((string) $v),
            'status' => fn ($v) => $this->normalizeStatus((string) $v),
            'title' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $this->sanitizeNullableString($v),
            'owner_user_id' => fn ($v) => $v,
            'mitigation' => fn ($v) => $this->sanitizeNullableString($v),
            'evidence_reference' => fn ($v) => $v,
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
     * @param array<string,mixed> $data
     */
    public function acceptRisk(SubscriptionRenewalRisk $risk, array $data, ?User $actor = null): SubscriptionRenewalRisk
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Accepted risk requires a reason.');
        }

        $requires = in_array($risk->severity, (array) config('subscription_renewal.accepted_risk_requires_expiry_for', []), true);
        $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
        $approver = $data['approver'] ?? $data['approver_id'] ?? $actor?->id;

        if ($requires && $expiresAt === null) {
            throw new InvalidArgumentException("Accepted risk for {$risk->severity} requires an expiry/review date.");
        }

        if ($requires && $approver === null) {
            throw new InvalidArgumentException("Accepted risk for {$risk->severity} requires an approver.");
        }

        $risk->status = SubscriptionRenewalRisk::STATUS_ACCEPTED_RISK;
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

    public function close(SubscriptionRenewalRisk $risk, ?User $actor = null): SubscriptionRenewalRisk
    {
        $risk->status = SubscriptionRenewalRisk::STATUS_CLOSED;
        $risk->save();

        return $risk->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $blocking = (array) config('subscription_renewal.blocking_risk_severities', []);
        $watch = (array) config('subscription_renewal.watch_risk_severities', []);

        $open = SubscriptionRenewalRisk::query()->open()->get();

        $openBySeverity = [];
        foreach (SubscriptionRenewalRisk::SEVERITIES as $severity) {
            $openBySeverity[$severity] = $open->where('severity', $severity)->count();
        }

        $openBlockingUnaccepted = $open
            ->filter(fn (SubscriptionRenewalRisk $r) => in_array($r->severity, $blocking, true))
            ->count();

        $openWatchNoMitigation = $open
            ->filter(fn (SubscriptionRenewalRisk $r) => in_array($r->severity, $watch, true) && trim((string) $r->mitigation) === '')
            ->count();

        $openWatchMitigated = $open
            ->filter(fn (SubscriptionRenewalRisk $r) => in_array($r->severity, $watch, true) && trim((string) $r->mitigation) !== '')
            ->count();

        $expiredAcceptedRiskBlocking = SubscriptionRenewalRisk::query()
            ->where('status', SubscriptionRenewalRisk::STATUS_ACCEPTED_RISK)
            ->whereIn('severity', $blocking)
            ->whereNotNull('accepted_risk_expires_at')
            ->where('accepted_risk_expires_at', '<', $now)
            ->count();

        $expiredAcceptedRiskWatch = SubscriptionRenewalRisk::query()
            ->where('status', SubscriptionRenewalRisk::STATUS_ACCEPTED_RISK)
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
        if (! in_array($severity, SubscriptionRenewalRisk::SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid severity: {$severity}");
        }

        return $severity;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SubscriptionRenewalRisk::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function normalizeArea(string $area): string
    {
        $area = strtoupper(trim($area));

        return in_array($area, SubscriptionRenewalRisk::AREAS, true) ? $area : SubscriptionRenewalRisk::AREA_OTHER;
    }

    private function generateReference(): string
    {
        return 'SRRISK-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
