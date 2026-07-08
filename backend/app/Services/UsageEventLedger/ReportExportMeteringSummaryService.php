<?php

namespace App\Services\UsageEventLedger;

use App\Models\TenantUsageEvent;

/**
 * Sprint 27 — the shared, redacted report export metering summary consumed by the
 * platform-admin summary endpoint and the report-export-metering:summary command.
 *
 * Counts only, no event payloads and no per-tenant PII (UEL-R013). The
 * current-month figure is derived from the append-only ledger for the canonical
 * `reports.exports.monthly` meter (UEL-R006).
 */
class ReportExportMeteringSummaryService
{
    public function __construct(
        private readonly UsageEventPeriodResolver $periods,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $meterKey = TenantUsageEvent::METER_REPORTS_EXPORTS_MONTHLY;
        $period = $this->periods->monthlyPeriodKey();

        $total = (int) TenantUsageEvent::query()->forMeter($meterKey)->sum('quantity');
        $currentMonth = (int) TenantUsageEvent::query()
            ->forMeter($meterKey)
            ->forPeriod($period)
            ->sum('quantity');
        $tenants = (int) TenantUsageEvent::query()
            ->forMeter($meterKey)
            ->distinct('tenant_id')
            ->count('tenant_id');
        $tenantsThisMonth = (int) TenantUsageEvent::query()
            ->forMeter($meterKey)
            ->forPeriod($period)
            ->distinct('tenant_id')
            ->count('tenant_id');

        return [
            'meter_key' => $meterKey,
            'event_key' => TenantUsageEvent::EVENT_REPORT_EXPORTED,
            'meterable' => (bool) (((array) config('tenant_plan.usage_limits', []))[$meterKey]['meterable'] ?? false),
            'period_key' => $period,
            'exports_total' => $total,
            'exports_current_month' => $currentMonth,
            'tenants_all_time' => $tenants,
            'tenants_current_month' => $tenantsThisMonth,
        ];
    }
}
