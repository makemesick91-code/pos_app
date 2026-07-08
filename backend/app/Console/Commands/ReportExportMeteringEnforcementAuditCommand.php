<?php

namespace App\Console\Commands;

use App\Services\UsageEventLedger\ReportExportMeteringEnforcementAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 27 — report-export-metering:enforcement-audit. Verifies the report
 * export routes carry the lifecycle → entitlement → usage guard chain in order
 * and the meter is live (UEL-R009/R010/R011). Exit code: 1 on NO_GO. Never prints
 * secrets.
 */
class ReportExportMeteringEnforcementAuditCommand extends Command
{
    protected $signature = 'report-export-metering:enforcement-audit {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Audit report export metering runtime enforcement (guards + meter).';

    public function handle(ReportExportMeteringEnforcementAuditService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Report Export Metering Enforcement Audit');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === ReportExportMeteringEnforcementAuditService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $report['decision'] === ReportExportMeteringEnforcementAuditService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
