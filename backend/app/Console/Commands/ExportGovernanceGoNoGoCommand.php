<?php

namespace App\Console\Commands;

use App\Services\ExportGovernance\ExportGovernanceGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 29 — export-governance:go-no-go. Aggregates the export governance
 * enforcement audit, the Sprint 29 command contract, the Sprint 25–28 prior gate
 * contract, the meterable check, and the Sprint 29 docs into a single
 * GO/WATCH/NO-GO decision (EGC-R014). Never prints secrets, never deploys, never
 * charges, never mutates the ledger. Exit code: 0 — GO/WATCH (unless --strict on
 * WATCH), 1 — NO_GO.
 */
class ExportGovernanceGoNoGoCommand extends Command
{
    protected $signature = 'export-governance:go-no-go {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Aggregate export governance audit + prior gates + docs into GO/WATCH/NO-GO.';

    public function handle(ExportGovernanceGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Export Governance GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === ExportGovernanceGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $report['decision'] === ExportGovernanceGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
