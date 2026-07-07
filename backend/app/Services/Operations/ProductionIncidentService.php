<?php

namespace App\Services\Operations;

use App\Models\ProductionIncident;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 19 — production incident lifecycle.
 *
 * Owns create/update/assign/status-transition/accept-risk for production
 * incidents, computes the SLA due timestamp from the incident severity, stamps
 * the SLA breach timestamp when an open incident passes its due time, and
 * summarizes incidents by severity/status/area/SLA into a GO/WATCH/NO_GO
 * decision. Tenant/store relationships are validated (a store must belong to the
 * given tenant). Secret-looking values are stripped from free-text/metadata so
 * the register can never leak credentials.
 *
 * Open P0/P1 without a valid accepted risk = NO_GO; open P2 = WATCH. Accepted
 * risk for P0/P1/P2 requires an approver, a reason, and an expiry. This service
 * never sends real alerts and never mutates business data.
 */
class ProductionIncidentService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** Key fragments (case-insensitive) whose values are redacted. */
    private const REDACTED_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'private_key',
        'server_key', 'client_secret', 'authorization', 'credential', 'app_key',
    ];

    private const REDACTION = '[REDACTED]';

    /**
     * Create an incident. `store_id` must belong to `tenant_id` when both set.
     *
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null, ?Carbon $now = null): ProductionIncident
    {
        $now ??= Carbon::now();
        $severity = $this->normalizeSeverity((string) ($attributes['severity'] ?? ''));
        $area = $this->normalizeArea((string) ($attributes['area'] ?? 'OTHER'));

        $tenantId = $attributes['tenant_id'] ?? null;
        $storeId = $attributes['store_id'] ?? null;
        $this->assertStoreBelongsToTenant($tenantId, $storeId);

        $detectedAt = isset($attributes['detected_at']) ? Carbon::parse($attributes['detected_at']) : $now;

        return ProductionIncident::query()->create([
            'incident_reference' => (string) ($attributes['incident_reference'] ?? $this->generateReference()),
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'reported_by' => $attributes['reported_by'] ?? $actor?->id,
            'assigned_to' => $attributes['assigned_to'] ?? null,
            'area' => $area,
            'severity' => $severity,
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? ProductionIncident::STATUS_OPEN)),
            'impact' => $this->sanitizeString((string) ($attributes['impact'] ?? 'UNKNOWN')),
            'title' => $this->sanitizeString((string) ($attributes['title'] ?? 'Untitled incident')),
            'description' => $this->sanitizeNullableString($attributes['description'] ?? null),
            'detected_at' => $detectedAt,
            'started_at' => isset($attributes['started_at']) ? Carbon::parse($attributes['started_at']) : null,
            'sla_due_at' => $this->computeSlaDueAt($severity, $detectedAt),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);
    }

    /**
     * Update editable fields. Severity changes recompute the SLA due timestamp;
     * status changes go through transitionStatus so timestamps stay consistent.
     *
     * @param array<string,mixed> $attributes
     */
    public function update(ProductionIncident $incident, array $attributes, ?User $actor = null): ProductionIncident
    {
        if (array_key_exists('tenant_id', $attributes) || array_key_exists('store_id', $attributes)) {
            $this->assertStoreBelongsToTenant(
                $attributes['tenant_id'] ?? $incident->tenant_id,
                $attributes['store_id'] ?? $incident->store_id,
            );
        }

        $newStatus = null;
        if (isset($attributes['status'])) {
            $newStatus = $this->normalizeStatus((string) $attributes['status']);
            unset($attributes['status']);
        }

        if (isset($attributes['severity'])) {
            $severity = $this->normalizeSeverity((string) $attributes['severity']);
            $incident->severity = $severity;
            $incident->sla_due_at = $this->computeSlaDueAt($severity, $incident->detected_at ?? $incident->created_at ?? Carbon::now());
            unset($attributes['severity']);
        }

        $map = [
            'title' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $this->sanitizeNullableString($v),
            'impact' => fn ($v) => $this->sanitizeString((string) $v),
            'resolution_summary' => fn ($v) => $this->sanitizeNullableString($v),
            'evidence_reference' => fn ($v) => $v,
            'area' => fn ($v) => $this->normalizeArea((string) $v),
            'tenant_id' => fn ($v) => $v,
            'store_id' => fn ($v) => $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $incident->{$key} = $caster($attributes[$key]);
            }
        }

        $incident->save();

        if ($newStatus !== null && $newStatus !== $incident->status) {
            $this->transitionStatus($incident, $newStatus, $actor);
        }

        return $incident->refresh();
    }

    public function assign(ProductionIncident $incident, ?int $userId, ?User $actor = null): ProductionIncident
    {
        $incident->assigned_to = $userId;
        $incident->save();

        return $incident->refresh();
    }

    /**
     * Conservative status transition. Stamps the matching timestamps and, for a
     * still-open incident past its SLA due time, records the SLA breach.
     */
    public function transitionStatus(ProductionIncident $incident, string $status, ?User $actor = null, ?Carbon $now = null): ProductionIncident
    {
        $now ??= Carbon::now();
        $status = $this->normalizeStatus($status);

        if ($status === $incident->status) {
            return $incident;
        }

        $incident->status = $status;

        match ($status) {
            ProductionIncident::STATUS_RESOLVED => $incident->resolved_at = $incident->resolved_at ?? $now,
            ProductionIncident::STATUS_CLOSED => $incident->closed_at = $now,
            default => null,
        };

        $incident->save();

        return $incident->refresh();
    }

    /**
     * Accept a blocking incident as a known risk. For P0/P1/P2 an approver, a
     * reason, and an expiry are required. The original severity is preserved.
     *
     * @param array<string,mixed> $data
     */
    public function acceptRisk(ProductionIncident $incident, array $data, ?User $actor = null): ProductionIncident
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Accepted risk requires a reason.');
        }

        $requiresExpiry = in_array($incident->severity, (array) config('production_operations.accepted_risk_requires_expiry_for', []), true);
        $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;
        $approver = $data['approver'] ?? $actor?->id;

        if ($requiresExpiry && $expiresAt === null) {
            throw new InvalidArgumentException("Accepted risk for {$incident->severity} requires an expiry/review date.");
        }

        if ($requiresExpiry && $approver === null) {
            throw new InvalidArgumentException("Accepted risk for {$incident->severity} requires an approver.");
        }

        $incident->status = ProductionIncident::STATUS_ACCEPTED_RISK;
        $incident->accepted_risk_at = Carbon::now();
        $incident->accepted_risk_by = $approver;
        $incident->accepted_risk_reason = $this->sanitizeString($reason);
        $incident->accepted_risk_expires_at = $expiresAt;
        if (isset($data['evidence_reference'])) {
            $incident->evidence_reference = $data['evidence_reference'];
        }
        $incident->save();

        return $incident->refresh();
    }

    /**
     * Stamp sla_breached_at on every open incident whose due time has passed.
     */
    public function detectSlaBreaches(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $count = 0;

        ProductionIncident::query()
            ->open()
            ->whereNull('sla_breached_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', $now)
            ->get()
            ->each(function (ProductionIncident $incident) use ($now, &$count): void {
                $incident->sla_breached_at = $now;
                $incident->save();
                $count++;
            });

        return $count;
    }

    /**
     * Aggregate open incidents by severity/status/area/SLA and derive a
     * GO/WATCH/NO_GO decision.
     *
     * @return array<string,mixed>
     */
    public function summary(?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $blocking = (array) config('production_operations.blocking_incident_severities', []);
        $watch = (array) config('production_operations.watch_incident_severities', []);

        $open = ProductionIncident::query()->open()->get();

        $openBySeverity = [];
        foreach (ProductionIncident::SEVERITIES as $severity) {
            $openBySeverity[$severity] = $open->where('severity', $severity)->count();
        }

        // A blocking incident is "unaccepted" if it is open and lacks a valid
        // accepted risk (accepted-risk incidents are not in the open set).
        $openBlockingUnaccepted = $open
            ->filter(fn (ProductionIncident $i) => in_array($i->severity, $blocking, true))
            ->count();

        $openWatch = $open
            ->filter(fn (ProductionIncident $i) => in_array($i->severity, $watch, true))
            ->count();

        $slaBreached = $open
            ->filter(fn (ProductionIncident $i) => $i->sla_due_at !== null && $i->sla_due_at->lt($now))
            ->count();

        $slaBreachedBlocking = $open
            ->filter(fn (ProductionIncident $i) => in_array($i->severity, $blocking, true) && $i->sla_due_at !== null && $i->sla_due_at->lt($now))
            ->count();

        $expiredAcceptedRiskBlocking = ProductionIncident::query()
            ->where('status', ProductionIncident::STATUS_ACCEPTED_RISK)
            ->whereIn('severity', $blocking)
            ->whereNotNull('accepted_risk_expires_at')
            ->where('accepted_risk_expires_at', '<', $now)
            ->count();

        $decision = self::DECISION_GO;
        if ($openBlockingUnaccepted > 0 || $slaBreachedBlocking > 0 || $expiredAcceptedRiskBlocking > 0) {
            $decision = self::DECISION_NO_GO;
        } elseif ($openWatch > 0 || $slaBreached > 0) {
            $decision = self::DECISION_WATCH;
        }

        return [
            'decision' => $decision,
            'counts' => [
                'open_total' => $open->count(),
                'open_by_severity' => $openBySeverity,
                'open_blocking_unaccepted' => $openBlockingUnaccepted,
                'open_watch' => $openWatch,
                'sla_breached' => $slaBreached,
                'sla_breached_blocking' => $slaBreachedBlocking,
                'expired_accepted_risk_blocking' => $expiredAcceptedRiskBlocking,
            ],
        ];
    }

    public function computeSlaDueAt(string $severity, Carbon $from): ?Carbon
    {
        $hours = (array) config('production_operations.incident_sla_hours', []);
        if (! isset($hours[$severity])) {
            return null;
        }

        return $from->copy()->addHours((int) $hours[$severity]);
    }

    private function assertStoreBelongsToTenant(mixed $tenantId, mixed $storeId): void
    {
        if ($storeId === null) {
            return;
        }

        $store = Store::query()->find($storeId);
        if ($store === null) {
            throw new InvalidArgumentException('Store does not exist.');
        }

        if ($tenantId === null || (int) $store->tenant_id !== (int) $tenantId) {
            throw new InvalidArgumentException('Store must belong to the given tenant.');
        }
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = strtoupper(trim($severity));
        if (! in_array($severity, ProductionIncident::SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid severity: {$severity}");
        }

        return $severity;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, ProductionIncident::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function normalizeArea(string $area): string
    {
        $area = strtoupper(trim($area));

        return in_array($area, ProductionIncident::AREAS, true) ? $area : 'OTHER';
    }

    private function generateReference(): string
    {
        return 'INC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function sanitizeString(string $value): string
    {
        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            $value = (string) preg_replace(
                '/('.preg_quote($fragment, '/').'\s*[:=]\s*)\S+/i',
                '$1'.self::REDACTION,
                $value,
            );
        }

        return $value;
    }

    private function sanitizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->sanitizeString((string) $value);
    }

    /**
     * @param array<string,mixed>|null $data
     * @return array<string,mixed>|null
     */
    private function sanitizeArray(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if ($this->isSecretKey((string) $key)) {
                $clean[$key] = self::REDACTION;

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $clean[$key] = $this->sanitizeString($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function isSecretKey(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
