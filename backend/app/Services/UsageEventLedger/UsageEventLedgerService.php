<?php

namespace App\Services\UsageEventLedger;

use App\Models\Tenant;
use App\Models\TenantUsageEvent;

/**
 * Sprint 27 — the high-level, read/append API over the usage event ledger.
 *
 * All reads are tenant-scoped: normal runtime never sees cross-tenant usage
 * events (UEL-R013). Monthly meters are derived by counting the quantity of
 * events for a meter_key within the current server-side period key (UEL-R005,
 * UEL-R006). Appends are delegated to the idempotent UsageEventRecorder
 * (UEL-R004). The service never mutates or deletes an existing event (UEL-R002).
 */
class UsageEventLedgerService
{
    public function __construct(
        private readonly UsageEventRecorder $recorder,
        private readonly UsageEventPeriodResolver $periods,
    ) {}

    /**
     * @param array<string,mixed>|null $metadata
     */
    public function append(
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
    ): UsageEventDecision {
        return $this->recorder->record(
            tenant: $tenant,
            eventKey: $eventKey,
            eventCategory: $eventCategory,
            meterKey: $meterKey,
            idempotencyKey: $idempotencyKey,
            period: $period,
            quantity: $quantity,
            source: $source,
            actorType: $actorType,
            actorId: $actorId,
            subjectType: $subjectType,
            subjectId: $subjectId,
            requestFingerprint: $requestFingerprint,
            metadata: $metadata,
        );
    }

    /**
     * Current monthly usage for a meter, summed from the ledger for this tenant.
     */
    public function monthlyMeterCount(Tenant $tenant, string $meterKey): int
    {
        return $this->meterCount($tenant, $meterKey, $this->periods->monthlyPeriodKey());
    }

    public function meterCount(Tenant $tenant, string $meterKey, string $periodKey): int
    {
        return (int) TenantUsageEvent::query()
            ->forTenant((int) $tenant->id)
            ->forMeter($meterKey)
            ->forPeriod($periodKey)
            ->sum('quantity');
    }

    /**
     * Per-meter, current-month usage summary for a single tenant (redacted;
     * counts only — never event payloads).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tenantSummary(Tenant $tenant): array
    {
        $period = $this->periods->monthlyPeriodKey();
        $rows = TenantUsageEvent::query()
            ->forTenant((int) $tenant->id)
            ->selectRaw('meter_key, event_category, count(*) as events, coalesce(sum(quantity),0) as quantity')
            ->whereNotNull('meter_key')
            ->groupBy('meter_key', 'event_category')
            ->get();

        return $rows->map(fn ($r) => [
            'meter_key' => (string) $r->meter_key,
            'event_category' => (string) $r->event_category,
            'events' => (int) $r->events,
            'quantity' => (int) $r->quantity,
            'current_month' => $this->meterCount($tenant, (string) $r->meter_key, $period),
            'period_key' => $period,
        ])->all();
    }

    /**
     * Cross-tenant, per-meter ledger summary for platform admin (counts only,
     * no event payloads, no per-tenant PII) — UEL-R013.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ledgerSummary(): array
    {
        $rows = TenantUsageEvent::query()
            ->selectRaw('meter_key, event_key, event_category, source, count(*) as events, coalesce(sum(quantity),0) as quantity, count(distinct tenant_id) as tenants')
            ->groupBy('meter_key', 'event_key', 'event_category', 'source')
            ->get();

        return $rows->map(fn ($r) => [
            'meter_key' => $r->meter_key === null ? null : (string) $r->meter_key,
            'event_key' => (string) $r->event_key,
            'event_category' => (string) $r->event_category,
            'source' => (string) $r->source,
            'tenants' => (int) $r->tenants,
            'events' => (int) $r->events,
            'quantity' => (int) $r->quantity,
        ])->all();
    }
}
