<?php

namespace App\Services\UsageLedgerAnomaly;

/**
 * Sprint 28 — turns a read-only anomaly scan into a set of governed repair
 * decisions WITHOUT touching the database (ULR-R007, dry-run by nature).
 *
 * Only anomalies the detector marks auto-repairable (duplicate double-count drift)
 * become an `apply` decision with a deterministic, idempotent repair_key derived
 * from the anomaly signature (ULR-R011). Everything else becomes a
 * `manual_review` decision — reported, never auto-mutated (ULR-R010).
 */
class UsageLedgerRepairPlanner
{
    public function __construct(
        private readonly UsageLedgerAnomalyDetector $detector,
    ) {}

    /**
     * @return array<int, UsageLedgerRepairDecision>
     */
    public function plan(?int $tenantId = null, ?string $meterKey = null): array
    {
        $decisions = [];
        foreach ($this->detector->scan($tenantId, $meterKey) as $anomaly) {
            $decisions[] = $this->decide($anomaly);
        }

        return $decisions;
    }

    /**
     * Only the applyable decisions from a plan.
     *
     * @return array<int, UsageLedgerRepairDecision>
     */
    public function autoRepairable(?int $tenantId = null, ?string $meterKey = null): array
    {
        return array_values(array_filter(
            $this->plan($tenantId, $meterKey),
            fn (UsageLedgerRepairDecision $d) => $d->isAutoRepairable(),
        ));
    }

    private function decide(UsageLedgerAnomaly $anomaly): UsageLedgerRepairDecision
    {
        if ($anomaly->autoRepairable && $anomaly->repairType !== null) {
            $repairKey = $anomaly->repairType.':'.substr(hash('sha256', (string) $anomaly->signature), 0, 40);

            return new UsageLedgerRepairDecision(
                action: UsageLedgerRepairDecision::ACTION_APPLY,
                anomalyType: $anomaly->type,
                severity: $anomaly->severity,
                tenantId: $anomaly->tenantId,
                meterKey: $anomaly->meterKey,
                periodKey: $anomaly->periodKey,
                repairType: $anomaly->repairType,
                quantityDelta: $anomaly->quantityDelta,
                repairKey: $repairKey,
                summary: 'Auto-repairable: '.$anomaly->summary,
                context: $anomaly->context,
            );
        }

        return new UsageLedgerRepairDecision(
            action: UsageLedgerRepairDecision::ACTION_MANUAL_REVIEW,
            anomalyType: $anomaly->type,
            severity: $anomaly->severity,
            tenantId: $anomaly->tenantId,
            meterKey: $anomaly->meterKey,
            periodKey: $anomaly->periodKey,
            repairType: null,
            quantityDelta: 0,
            repairKey: 'manual:'.substr(hash('sha256', $anomaly->type.'|'.json_encode($anomaly->context)), 0, 40),
            summary: 'Manual review required: '.$anomaly->summary,
            context: $anomaly->context,
        );
    }
}
