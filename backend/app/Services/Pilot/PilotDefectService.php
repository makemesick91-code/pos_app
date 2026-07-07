<?php

namespace App\Services\Pilot;

use App\Models\PilotDefect;
use App\Models\PilotDefectEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation.
 *
 * Owns the pilot defect lifecycle: create, update, assign, and status
 * transitions. Every lifecycle change appends an immutable PilotDefectEvent
 * (history is never deleted). Tenant/store relationships are validated (a store
 * must belong to the given tenant). Secret-looking values are stripped from
 * free-text/metadata/environment before persistence so the register can never
 * leak credentials. The blocking flag defaults from severity unless explicitly
 * overridden.
 *
 * This service never sends real alerts and never mutates business data.
 */
class PilotDefectService
{
    /** Key fragments (case-insensitive) whose values are redacted. */
    private const REDACTED_KEY_FRAGMENTS = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'private_key',
        'server_key', 'client_secret', 'authorization', 'credential', 'app_key',
    ];

    private const REDACTION = '[REDACTED]';

    /**
     * Create a defect. `store_id` must belong to `tenant_id` when both are set.
     * blocking defaults to true for BLOCKER/CRITICAL unless overridden.
     *
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes, ?User $actor = null): PilotDefect
    {
        $severity = $this->normalizeSeverity((string) ($attributes['severity'] ?? ''));
        $area = $this->normalizeArea((string) ($attributes['area'] ?? PilotDefect::AREAS[13]));

        $tenantId = $attributes['tenant_id'] ?? null;
        $storeId = $attributes['store_id'] ?? null;
        $this->assertStoreBelongsToTenant($tenantId, $storeId);

        $blocking = array_key_exists('blocking', $attributes)
            ? (bool) $attributes['blocking']
            : $this->defaultBlocking($severity);

        $defect = PilotDefect::query()->create([
            'defect_reference' => (string) ($attributes['defect_reference'] ?? $this->generateReference()),
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'reported_by' => $attributes['reported_by'] ?? $actor?->id,
            'assigned_to' => $attributes['assigned_to'] ?? null,
            'area' => $area,
            'severity' => $severity,
            'status' => $this->normalizeStatus((string) ($attributes['status'] ?? PilotDefect::STATUS_OPEN)),
            'blocking' => $blocking,
            'title' => $this->sanitizeString((string) ($attributes['title'] ?? 'Untitled defect')),
            'description' => $this->sanitizeNullableString($attributes['description'] ?? null),
            'steps_to_reproduce' => $this->sanitizeNullableString($attributes['steps_to_reproduce'] ?? null),
            'expected_result' => $this->sanitizeNullableString($attributes['expected_result'] ?? null),
            'actual_result' => $this->sanitizeNullableString($attributes['actual_result'] ?? null),
            'environment' => $this->sanitizeArray($attributes['environment'] ?? null),
            'evidence_reference' => $attributes['evidence_reference'] ?? null,
            'sla_due_at' => $this->computeSlaDueAt($severity, Carbon::now()),
            'metadata' => $this->sanitizeArray($attributes['metadata'] ?? null),
        ]);

        $this->appendEvent($defect, PilotDefectEvent::TYPE_CREATED, $actor, [
            'to_status' => $defect->status,
            'to_severity' => $defect->severity,
            'message' => 'Defect created.',
        ]);

        if ($defect->assigned_to !== null) {
            $this->appendEvent($defect, PilotDefectEvent::TYPE_ASSIGNED, $actor, [
                'message' => 'Defect assigned on creation.',
                'payload' => ['assigned_to' => $defect->assigned_to],
            ]);
        }

        return $defect->refresh();
    }

    /**
     * Update editable fields. Severity changes append a SEVERITY_CHANGED event;
     * status changes go through transitionStatus so they are always tracked.
     *
     * @param array<string,mixed> $attributes
     */
    public function update(PilotDefect $defect, array $attributes, ?User $actor = null): PilotDefect
    {
        if (array_key_exists('tenant_id', $attributes) || array_key_exists('store_id', $attributes)) {
            $this->assertStoreBelongsToTenant(
                $attributes['tenant_id'] ?? $defect->tenant_id,
                $attributes['store_id'] ?? $defect->store_id,
            );
        }

        $newStatus = null;
        if (isset($attributes['status'])) {
            $newStatus = $this->normalizeStatus((string) $attributes['status']);
            unset($attributes['status']);
        }

        if (isset($attributes['severity'])) {
            $this->changeSeverity($defect, $this->normalizeSeverity((string) $attributes['severity']), $actor);
            unset($attributes['severity']);
        }

        $map = [
            'title' => fn ($v) => $this->sanitizeString((string) $v),
            'description' => fn ($v) => $this->sanitizeNullableString($v),
            'steps_to_reproduce' => fn ($v) => $this->sanitizeNullableString($v),
            'expected_result' => fn ($v) => $this->sanitizeNullableString($v),
            'actual_result' => fn ($v) => $this->sanitizeNullableString($v),
            'environment' => fn ($v) => $this->sanitizeArray($v),
            'evidence_reference' => fn ($v) => $v,
            'area' => fn ($v) => $this->normalizeArea((string) $v),
            'tenant_id' => fn ($v) => $v,
            'store_id' => fn ($v) => $v,
            'blocking' => fn ($v) => (bool) $v,
            'metadata' => fn ($v) => $this->sanitizeArray($v),
        ];

        $dirty = [];
        foreach ($map as $key => $caster) {
            if (array_key_exists($key, $attributes)) {
                $dirty[$key] = $caster($attributes[$key]);
            }
        }

        if ($dirty !== []) {
            $defect->fill($dirty)->save();
            $this->appendEvent($defect, PilotDefectEvent::TYPE_UPDATED, $actor, [
                'message' => 'Defect fields updated.',
                'payload' => ['fields' => array_keys($dirty)],
            ]);
        }

        if ($newStatus !== null && $newStatus !== $defect->status) {
            $this->transitionStatus($defect, $newStatus, $actor);
        }

        return $defect->refresh();
    }

    public function assign(PilotDefect $defect, ?int $userId, ?User $actor = null): PilotDefect
    {
        $from = $defect->assigned_to;
        $defect->assigned_to = $userId;
        $defect->save();

        $this->appendEvent($defect, PilotDefectEvent::TYPE_ASSIGNED, $actor, [
            'message' => $userId === null ? 'Defect unassigned.' : 'Defect assigned.',
            'payload' => ['from' => $from, 'to' => $userId],
        ]);

        return $defect->refresh();
    }

    /**
     * Conservative status transition. Records STATUS_CHANGED plus a typed event
     * (FIXED/RETEST_REQUESTED/VERIFIED/CLOSED) where relevant and stamps the
     * matching timestamps. History is preserved.
     */
    public function transitionStatus(PilotDefect $defect, string $status, ?User $actor = null): PilotDefect
    {
        $status = $this->normalizeStatus($status);
        $from = $defect->status;

        if ($status === $from) {
            return $defect;
        }

        $defect->status = $status;

        match ($status) {
            PilotDefect::STATUS_FIXED => $defect->fixed_at = $defect->fixed_at ?? Carbon::now(),
            PilotDefect::STATUS_CLOSED => $defect->closed_at = Carbon::now(),
            default => null,
        };

        $defect->save();

        $this->appendEvent($defect, PilotDefectEvent::TYPE_STATUS_CHANGED, $actor, [
            'from_status' => $from,
            'to_status' => $status,
            'message' => "Status changed from {$from} to {$status}.",
        ]);

        // VERIFIED is intentionally excluded here — FixVerificationService owns the
        // single VERIFIED event so the retest result/evidence is captured once.
        $typed = match ($status) {
            PilotDefect::STATUS_FIXED => PilotDefectEvent::TYPE_FIXED,
            PilotDefect::STATUS_RETEST => PilotDefectEvent::TYPE_RETEST_REQUESTED,
            PilotDefect::STATUS_CLOSED => PilotDefectEvent::TYPE_CLOSED,
            default => null,
        };

        if ($typed !== null) {
            $this->appendEvent($defect, $typed, $actor, ['to_status' => $status]);
        }

        return $defect->refresh();
    }

    public function comment(PilotDefect $defect, string $message, ?User $actor = null, ?string $evidenceReference = null): PilotDefectEvent
    {
        return $this->appendEvent($defect, PilotDefectEvent::TYPE_COMMENTED, $actor, [
            'message' => $this->sanitizeString($message),
            'evidence_reference' => $evidenceReference,
        ]);
    }

    /**
     * Append an immutable lifecycle event. The only write path for events.
     *
     * @param array<string,mixed> $data
     */
    public function appendEvent(PilotDefect $defect, string $type, ?User $actor, array $data = []): PilotDefectEvent
    {
        return $defect->events()->create([
            'actor_user_id' => $actor?->id,
            'event_type' => $type,
            'from_status' => $data['from_status'] ?? null,
            'to_status' => $data['to_status'] ?? null,
            'from_severity' => $data['from_severity'] ?? null,
            'to_severity' => $data['to_severity'] ?? null,
            'message' => isset($data['message']) ? $this->sanitizeString((string) $data['message']) : null,
            'payload' => $this->sanitizeArray($data['payload'] ?? null),
            'evidence_reference' => $data['evidence_reference'] ?? null,
        ]);
    }

    public function defaultBlocking(string $severity): bool
    {
        return in_array($severity, (array) config('pilot_stabilization.blocking_severities', []), true);
    }

    public function computeSlaDueAt(string $severity, Carbon $from): ?Carbon
    {
        $hours = (array) config('pilot_stabilization.severity_sla_hours', []);
        if (! isset($hours[$severity])) {
            return null;
        }

        return $from->copy()->addHours((int) $hours[$severity]);
    }

    private function changeSeverity(PilotDefect $defect, string $severity, ?User $actor): void
    {
        if ($severity === $defect->severity) {
            return;
        }

        $from = $defect->severity;
        $defect->severity = $severity;
        // Recompute the default blocking flag unless the defect is under accepted
        // risk (accepted risk must never silently drop the blocking flag).
        if (! $defect->isAcceptedRisk()) {
            $defect->blocking = $this->defaultBlocking($severity);
        }
        $defect->sla_due_at = $this->computeSlaDueAt($severity, $defect->created_at ?? Carbon::now());
        $defect->save();

        $this->appendEvent($defect, PilotDefectEvent::TYPE_SEVERITY_CHANGED, $actor, [
            'from_severity' => $from,
            'to_severity' => $severity,
            'message' => "Severity changed from {$from} to {$severity}.",
        ]);
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
        if (! in_array($severity, PilotDefect::SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid severity: {$severity}");
        }

        return $severity;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        if (! in_array($status, PilotDefect::STATUSES, true)) {
            throw new InvalidArgumentException("Invalid status: {$status}");
        }

        return $status;
    }

    private function normalizeArea(string $area): string
    {
        $area = strtoupper(trim($area));

        return in_array($area, PilotDefect::AREAS, true) ? $area : 'OTHER';
    }

    private function generateReference(): string
    {
        return 'DEF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function sanitizeString(string $value): string
    {
        // Redact obvious "key=secret" / "key: secret" fragments in free text.
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
