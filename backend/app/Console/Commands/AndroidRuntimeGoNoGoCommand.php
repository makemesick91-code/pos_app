<?php

namespace App\Console\Commands;

use App\Services\AndroidRuntime\AndroidRuntimeGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:go-no-go. The hard Sprint 34 gate (ADR-R030).
 * Aggregates governance, command self-contract, prior Sprint 24–33 gates, runtime
 * service wiring and commercial-chain compatibility into one GO/WATCH/NO-GO.
 *
 * Never prints secrets, never deploys, never charges, never marks paid, never
 * lifts a suspension, never runs Android Gradle. Exit: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO_GO.
 */
class AndroidRuntimeGoNoGoCommand extends Command
{
    protected $signature = 'android-runtime:go-no-go {--json : Output JSON} {--strict : Fail on warnings}';

    protected $description = 'Aggregate Android runtime governance + command + chain checks into GO/WATCH/NO-GO.';

    public function handle(AndroidRuntimeGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Android Runtime GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === AndroidRuntimeGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['decision'] === AndroidRuntimeGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
