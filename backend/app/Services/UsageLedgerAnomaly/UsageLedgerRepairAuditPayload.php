<?php

namespace App\Services\UsageLedgerAnomaly;

use App\Services\UsageEventLedger\SanitizesUsageEventMetadata;

/**
 * Sprint 28 — builds the redacted metadata payload persisted with a governed
 * repair record and its admin audit log entry (ULR-R006, ULR-R008). Runs the
 * combined reason/actor/decision context through the shared usage-event
 * sanitizer so a repair audit trail can never leak a secret.
 */
class UsageLedgerRepairAuditPayload
{
    use SanitizesUsageEventMetadata;

    /**
     * @return array<string, mixed>
     */
    public function build(UsageLedgerRepairDecision $decision, string $reason, string $actor): array
    {
        return (array) $this->sanitizeMetadata([
            'reason' => $reason,
            'actor' => $actor,
            'anomaly_type' => $decision->anomalyType,
            'repair_type' => $decision->repairType,
            'meter_key' => $decision->meterKey,
            'period_key' => $decision->periodKey,
            'quantity_delta' => $decision->quantityDelta,
            'repair_key' => $decision->repairKey,
            'context' => $decision->context,
        ]);
    }
}
