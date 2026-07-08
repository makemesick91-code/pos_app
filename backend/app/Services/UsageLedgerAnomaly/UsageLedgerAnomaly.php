<?php

namespace App\Services\UsageLedgerAnomaly;

/**
 * Sprint 28 — a single, already-redacted usage-ledger anomaly (ULR-R001, R006).
 *
 * Anomalies are produced read-only by the detector and never carry raw secret
 * values: `context` holds only counts, ids, meter/period keys, and redacted
 * markers. `autoRepairable` marks anomalies the governed repair planner may
 * propose a correction for (currently only duplicate double-count drift);
 * everything else is manual-review-only (ULR-R010).
 */
final class UsageLedgerAnomaly
{
    // Anomaly type taxonomy.
    public const TYPE_DUPLICATE_IDEMPOTENCY = 'duplicate_idempotency';
    public const TYPE_MISSING_REQUIRED_FIELD = 'missing_required_field';
    public const TYPE_INVALID_QUANTITY = 'invalid_quantity';
    public const TYPE_INVALID_PERIOD = 'invalid_period';
    public const TYPE_UNKNOWN_METER = 'unknown_meter';
    public const TYPE_UNSANITIZED_METADATA = 'unsanitized_metadata';

    /**
     * @param array<string, mixed> $context redacted; counts/ids/keys only
     */
    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly ?int $tenantId,
        public readonly ?string $meterKey,
        public readonly ?string $periodKey,
        public readonly string $summary,
        public readonly array $context = [],
        public readonly bool $autoRepairable = false,
        public readonly ?string $repairType = null,
        public readonly int $quantityDelta = 0,
        public readonly ?string $signature = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'tenant_id' => $this->tenantId,
            'meter_key' => $this->meterKey,
            'period_key' => $this->periodKey,
            'summary' => $this->summary,
            'context' => $this->context,
            'auto_repairable' => $this->autoRepairable,
            'repair_type' => $this->repairType,
            'quantity_delta' => $this->quantityDelta,
        ];
    }
}
