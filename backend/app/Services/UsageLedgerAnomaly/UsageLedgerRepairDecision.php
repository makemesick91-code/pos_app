<?php

namespace App\Services\UsageLedgerAnomaly;

/**
 * Sprint 28 — a single, redacted governed repair decision produced by the planner
 * (ULR-R007). It describes WHAT the repair would do without performing it.
 *
 * action = apply → an auto-repairable correction the repair-apply command may
 * persist as a governed repair record. action = manual_review → an anomaly that is
 * NOT safe to auto-repair and must be handled by a human (ULR-R010).
 */
final class UsageLedgerRepairDecision
{
    public const ACTION_APPLY = 'apply';
    public const ACTION_MANUAL_REVIEW = 'manual_review';

    /**
     * @param array<string, mixed> $context redacted; counts/ids/keys only
     */
    public function __construct(
        public readonly string $action,
        public readonly string $anomalyType,
        public readonly string $severity,
        public readonly ?int $tenantId,
        public readonly ?string $meterKey,
        public readonly ?string $periodKey,
        public readonly ?string $repairType,
        public readonly int $quantityDelta,
        public readonly string $repairKey,
        public readonly string $summary,
        public readonly array $context = [],
    ) {}

    public function isAutoRepairable(): bool
    {
        return $this->action === self::ACTION_APPLY;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'anomaly_type' => $this->anomalyType,
            'severity' => $this->severity,
            'tenant_id' => $this->tenantId,
            'meter_key' => $this->meterKey,
            'period_key' => $this->periodKey,
            'repair_type' => $this->repairType,
            'quantity_delta' => $this->quantityDelta,
            'repair_key' => $this->repairKey,
            'summary' => $this->summary,
            'context' => $this->context,
        ];
    }
}
