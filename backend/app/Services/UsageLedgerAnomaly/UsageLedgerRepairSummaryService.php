<?php

namespace App\Services\UsageLedgerAnomaly;

use App\Models\TenantUsageLedgerRepair;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sprint 28 — read-only, redacted governed repair history for the CLI and the
 * platform-admin API (ULR-R008, ULR-R012). Never mutates anything; metadata is
 * already redacted at write time.
 */
class UsageLedgerRepairSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $tenantId = null, ?string $meterKey = null): array
    {
        $rows = TenantUsageLedgerRepair::query()
            ->when($tenantId !== null, fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->when($meterKey !== null, fn (Builder $q) => $q->where('meter_key', $meterKey))
            ->orderByDesc('applied_at')
            ->get();

        return [
            'total_repairs' => $rows->count(),
            'net_quantity_delta' => (int) $rows->sum('quantity_delta'),
            'by_type' => $rows->groupBy('repair_type')->map->count()->all(),
            'repairs' => $rows->map(fn (TenantUsageLedgerRepair $r) => [
                'id' => (int) $r->id,
                'tenant_id' => (int) $r->tenant_id,
                'meter_key' => (string) $r->meter_key,
                'period_key' => (string) $r->period_key,
                'repair_type' => (string) $r->repair_type,
                'repair_key' => (string) $r->repair_key,
                'quantity_delta' => (int) $r->quantity_delta,
                'reason' => (string) $r->reason,
                'applied_by' => (string) $r->applied_by,
                'applied_at' => $r->applied_at?->toIso8601String(),
                'metadata' => (array) ($r->metadata ?? []),
            ])->all(),
        ];
    }
}
