<?php

namespace App\Console\Commands;

use App\Services\SupportOperations\SupportGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:go-no-go. The hard Sprint 35 gate (SUP-R030).
 * Aggregates governance, command self-contract, prior Sprint 24–34 gates, support
 * service wiring and commercial-chain compatibility into one GO/WATCH/NO-GO.
 *
 * Never prints secrets, never deploys, never charges, never marks paid, never
 * lifts a suspension, never runs Android Gradle. Exit: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO_GO.
 */
class SupportOpsGoNoGoCommand extends Command
{
    protected $signature = 'support-ops:go-no-go {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Aggregate support-operations governance + command + chain checks into GO/WATCH/NO-GO.';

    public function handle(SupportGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Support Operations GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === SupportGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['decision'] === SupportGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
