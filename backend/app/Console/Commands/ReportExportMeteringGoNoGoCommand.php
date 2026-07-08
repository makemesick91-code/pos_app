<?php

namespace App\Console\Commands;

use App\Services\UsageEventLedger\ReportExportMeteringGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 27 — report-export-metering:go-no-go. Aggregates usage event ledger
 * readiness, the report export enforcement audit, the Sprint 27 command contract,
 * the Sprint 24–26 prior gate contract, and the Sprint 27 docs into a single
 * GO/WATCH/NO-GO decision (UEL-R014). Never prints secrets, never deploys, never
 * charges, never mutates the ledger. Exit code: 0 — GO/WATCH (unless --strict on
 * WATCH), 1 — NO_GO.
 */
class ReportExportMeteringGoNoGoCommand extends Command
{
    protected $signature = 'report-export-metering:go-no-go {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Aggregate usage event ledger readiness + enforcement audit + prior gates into GO/WATCH/NO-GO.';

    public function handle(ReportExportMeteringGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Report Export Metering GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === ReportExportMeteringGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $report['decision'] === ReportExportMeteringGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
