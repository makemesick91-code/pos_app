<?php

namespace App\Services\UsageLedgerAnomaly;

use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\TenantUsageLedgerRepair;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use App\Services\UsageEventLedger\UsageEventLedgerService;
use Illuminate\Support\Carbon;

/**
 * Sprint 28 — the ONLY writer of governed usage-ledger repairs (ULR-R007..R011).
 *
 * Safety contract:
 *  - Default is dry-run; the caller must pass an explicit apply intent (ULR-R007).
 *  - Apply requires a reason and an actor and is audit-logged (ULR-R008).
 *  - It NEVER updates or deletes an append-only ledger event — it only appends a
 *    signed correction record (ULR-R009, ULR-R010).
 *  - Correction deltas are clamped so effective usage can never go negative
 *    (ULR-R013).
 *  - Re-applying the same plan is idempotent via the per-tenant unique repair_key,
 *    so it can never create correction drift (ULR-R011).
 *  - Only auto-repairable decisions are applied; manual-review anomalies are
 *    skipped (ULR-R010).
 */
class UsageLedgerRepairService
{
    public function __construct(
        private readonly UsageEventLedgerService $ledger,
        private readonly UsageLedgerRepairAuditPayload $auditPayload,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param array<int, UsageLedgerRepairDecision> $decisions
     * @return array<string, mixed>
     */
    public function apply(
        array $decisions,
        string $reason,
        string $actor,
        bool $dryRun = true,
        ?User $auditActor = null,
    ): array {
        $applied = [];
        $skippedManual = 0;
        $alreadyApplied = 0;

        foreach ($decisions as $decision) {
            if (! $decision->isAutoRepairable() || $decision->tenantId === null || $decision->meterKey === null) {
                $skippedManual++;

                continue;
            }

            $tenant = Tenant::find($decision->tenantId);
            if ($tenant === null) {
                continue;
            }
            $period = (string) $decision->periodKey;

            // Idempotency: a repair for this deterministic repair_key already
            // exists → do not apply again (no drift).
            $existing = TenantUsageLedgerRepair::query()
                ->forTenant((int) $tenant->id)
                ->where('repair_key', $decision->repairKey)
                ->first();
            if ($existing !== null) {
                $alreadyApplied++;

                continue;
            }

            // Clamp so effective usage can never become negative (ULR-R013).
            $base = max(0, $this->ledger->rawMeterCount($tenant, $decision->meterKey, $period)
                + $this->ledger->repairDelta($tenant, $decision->meterKey, $period));
            $delta = max($decision->quantityDelta, -$base);

            $payload = $this->auditPayload->build($decision, $reason, $actor);

            if ($dryRun) {
                $applied[] = [
                    'dry_run' => true,
                    'tenant_id' => (int) $tenant->id,
                    'meter_key' => $decision->meterKey,
                    'period_key' => $period,
                    'repair_type' => $decision->repairType,
                    'repair_key' => $decision->repairKey,
                    'quantity_delta' => $delta,
                    'effective_before' => $base,
                    'effective_after' => max(0, $base + $delta),
                ];

                continue;
            }

            $repair = TenantUsageLedgerRepair::query()->create([
                'tenant_id' => $tenant->id,
                'meter_key' => $decision->meterKey,
                'period_key' => $period,
                'repair_key' => $decision->repairKey,
                'repair_type' => (string) $decision->repairType,
                'quantity_delta' => $delta,
                'reason' => $reason,
                'applied_by' => $actor,
                'applied_at' => Carbon::now(),
                'dry_run_payload' => null,
                'metadata' => $payload,
            ]);

            if ($auditActor !== null) {
                $this->audit->log(
                    actor: $auditActor,
                    action: AdminAuditLog::ACTION_USAGE_LEDGER_REPAIR_APPLIED,
                    targetType: AdminAuditLog::TARGET_TENANT_USAGE_LEDGER_REPAIR,
                    targetId: (int) $repair->id,
                    tenantId: (int) $tenant->id,
                    after: $payload,
                    metadata: ['effective_before' => $base, 'effective_after' => max(0, $base + $delta)],
                );
            }

            $applied[] = [
                'dry_run' => false,
                'repair_id' => (int) $repair->id,
                'tenant_id' => (int) $tenant->id,
                'meter_key' => $decision->meterKey,
                'period_key' => $period,
                'repair_type' => $decision->repairType,
                'repair_key' => $decision->repairKey,
                'quantity_delta' => $delta,
                'effective_before' => $base,
                'effective_after' => max(0, $base + $delta),
                'audit_logged' => $auditActor !== null,
            ];
        }

        return [
            'dry_run' => $dryRun,
            'reason' => $reason,
            'actor' => $actor,
            'applied' => $applied,
            'applied_count' => count($applied),
            'skipped_manual_review' => $skippedManual,
            'already_applied' => $alreadyApplied,
        ];
    }
}
