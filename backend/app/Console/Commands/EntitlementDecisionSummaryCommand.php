<?php

namespace App\Console\Commands;

use App\Services\Entitlements\EntitlementAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 32 — entitlement:decision-summary. Summarizes persisted denied /
 * degraded / bypassed / read_only decisions grouped by decision and reason code.
 * No PII, no secrets (rows are redacted at write time — ENT-R018/R020).
 */
class EntitlementDecisionSummaryCommand extends Command
{
    protected $signature = 'entitlement:decision-summary
        {--tenant= : Tenant id filter}
        {--json : Output JSON}';

    protected $description = 'Summarize denied/degraded/bypassed entitlement decisions (safe, no PII).';

    public function handle(EntitlementAuditService $audit): int
    {
        $tenantId = $this->option('tenant') !== null && $this->option('tenant') !== ''
            ? (int) $this->option('tenant')
            : null;

        $summary = $audit->summary($tenantId);

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Entitlement Decision Summary'.($tenantId !== null ? " (tenant #{$tenantId})" : ''));
        $this->line('  total decisions: '.$summary['total']);
        foreach ($summary['by_decision'] as $decision => $count) {
            $this->line("  {$decision}: {$count}");
        }
        foreach ($summary['by_reason_code'] as $reason => $count) {
            $this->line("  reason {$reason}: {$count}");
        }

        return self::SUCCESS;
    }
}
