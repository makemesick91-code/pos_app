<?php

namespace App\Services\BillingCollection;

use App\Models\SaasBillingCollectionRisk;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 23 — SaaS billing collection risk lifecycle.
 *
 * Owns create/update/accept-risk/close for billing collection risks and
 * summarizes risks by severity/status/area into a GO/WATCH/NO_GO decision. Open
 * CRITICAL/HIGH without a valid accepted risk = NO_GO; open MEDIUM without
 * mitigation = WATCH. Accepted risk for CRITICAL/HIGH/MEDIUM requires an approver,
 * a reason, and an expiry; an expired accepted risk re-blocks. Secret-looking
 * values are stripped from free-text/metadata.
 */
class BillingCollectionRiskGovernanceService
{
    use SanitizesBillingCollectionText;

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): SaasBillingCollectionRisk
    {
        return SaasBillingCollectionRisk::query()->create([
            'risk_reference' => (string) ($attributes['risk_reference'] ?? $this->generateReference()),
            'billing_account_id' => $attributes['billing_account_id'] ?? null,
            'invoice_id' => $attributes['invoice_id'] ?? null,
            'area' => $this->normalizeArea((string) ($attributes['area'] ?? SaasBillingCollectionRisk::AREA_OTHER)),
            'severity' => $this->normalizeSeverity((string) ($attributes['severity'] ?? '')),
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? SaasBillingCollectionRisk::STATUS_OPEN)),
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
    public function update(SaasBillingCollectionRisk $risk, array $attributes, ?User $actor = null): SaasBillingCollectionRisk
    {
        $map = [
            'billing_account_id' => fn ($v) => $v,
            'invoice_id' => fn ($v) => $v,
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
    public function acceptRisk(SaasBillingCollectionRisk $risk, array $data, ?User $actor = null): SaasBillingCollectionRisk
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Accepted risk requires a reason.');
        }

        $requires = in_array($risk->severity, (array) config('billing_collection.accepted_risk_requires_expiry_for', []), true);
        $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
        $approver = $data['approver'] ?? $data['approver_id'] ?? $actor?->id;

        if ($requires && $expiresAt === null) {
            throw new InvalidArgumentException("Accepted risk for {$risk->severity} requires an expiry/review date.");
        }

        if ($requires && $approver === null) {
            throw new InvalidArgumentException("Accepted risk for {$risk->severity} requires an approver.");
        }

        $risk->status = SaasBillingCollectionRisk::STATUS_ACCEPTED_RISK;
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

    public function close(SaasBillingCollectionRisk $risk, ?User $actor = null): SaasBillingCollectionRisk
    {
        $risk->status = SaasBillingCollectionRisk::STATUS_CLOSED;
        $risk->save();

        return $risk->refresh();
    }

    /**
     * @return array<string,mixed>
     */
    public function summary(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $blocking = (array) config('billing_collection.blocking_risk_severities', []);
        $watch = (array) config('billing_collection.watch_risk_severities', []);

        $open = SaasBillingCollectionRisk::query()->open()->get();

        $openBySeverity = [];
        foreach (SaasBillingCollectionRisk::SEVERITIES as $severity) {
            $openBySeverity[$severity] = $open->where('severity', $severity)->count();
        }

        $openBlockingUnaccepted = $open
            ->filter(fn (SaasBillingCollectionRisk $r) => in_array($r->severity, $blocking, true))
            ->count();

        $openWatchNoMitigation = $open
            ->filter(fn (SaasBillingCollectionRisk $r) => in_array($r->severity, $watch, true) && trim((string) $r->mitigation) === '')
            ->count();

        $openWatchMitigated = $open
            ->filter(fn (SaasBillingCollectionRisk $r) => in_array($r->severity, $watch, true) && trim((string) $r->mitigation) !== '')
            ->count();

        $expiredAcceptedRiskBlocking = SaasBillingCollectionRisk::query()
            ->where('status', SaasBillingCollectionRisk::STATUS_ACCEPTED_RISK)
            ->whereIn('severity', $blocking)
            ->whereNotNull('accepted_risk_expires_at')
            ->where('accepted_risk_expires_at', '<', $now)
            ->count();

        $expiredAcceptedRiskWatch = SaasBillingCollectionRisk::query()
            ->where('status', SaasBillingCollectionRisk::STATUS_ACCEPTED_RISK)
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
        if (! in_array($severity, SaasBillingCollectionRisk::SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid severity: {$severity}");
        }

        return $severity;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, SaasBillingCollectionRisk::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function normalizeArea(string $area): string
    {
        $area = strtoupper(trim($area));

        return in_array($area, SaasBillingCollectionRisk::AREAS, true) ? $area : SaasBillingCollectionRisk::AREA_OTHER;
    }

    private function generateReference(): string
    {
        return 'BCRISK-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
