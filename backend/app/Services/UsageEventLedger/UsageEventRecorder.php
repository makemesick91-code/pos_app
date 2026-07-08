<?php

namespace App\Services\UsageEventLedger;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use Illuminate\Support\Carbon;

/**
 * Sprint 27 — the single writer for the usage event ledger (UEL-R001).
 *
 * record() appends exactly one event per (tenant, idempotency_key). A retried
 * request that reuses the same idempotency key returns the existing event WITHOUT
 * counting again (UEL-R004). occurred_at and period_key are always derived
 * server-side (UEL-R005) and metadata is redacted before persistence (UEL-R003).
 * The ledger is append-only: this recorder never updates or deletes an existing
 * event (UEL-R002).
 */
class UsageEventRecorder
{
    use SanitizesUsageEventMetadata;

    public function __construct(
        private readonly UsageEventPeriodResolver $periods,
    ) {}

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function record(
        Tenant $tenant,
        string $eventKey,
        string $eventCategory,
        ?string $meterKey,
        string $idempotencyKey,
        string $period = 'monthly',
        int $quantity = 1,
        string $source = TenantUsageEvent::SOURCE_API,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $requestFingerprint = null,
        ?array $metadata = null,
        ?Carbon $occurredAt = null,
    ): UsageEventDecision {
        $occurredAt ??= Carbon::now();
        $periodKey = $this->periods->periodKeyFor($period, $occurredAt);

        $existing = TenantUsageEvent::query()
            ->forTenant((int) $tenant->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return new UsageEventDecision(
                recorded: false,
                duplicate: true,
                eventKey: $eventKey,
                meterKey: $meterKey,
                periodKey: $existing->period_key,
                event: $existing,
            );
        }

        $event = TenantUsageEvent::query()->create([
            'tenant_id' => $tenant->id,
            'event_key' => $eventKey,
            'event_category' => $eventCategory,
            'meter_key' => $meterKey,
            'quantity' => max(1, $quantity),
            'occurred_at' => $occurredAt,
            'period_key' => $periodKey,
            'idempotency_key' => $idempotencyKey,
            'source' => $source,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'request_fingerprint' => $this->sanitizeNullableString($requestFingerprint),
            'metadata' => $this->sanitizeMetadata($metadata),
        ]);

        return new UsageEventDecision(
            recorded: true,
            duplicate: false,
            eventKey: $eventKey,
            meterKey: $meterKey,
            periodKey: $periodKey,
            event: $event,
        );
    }
}
