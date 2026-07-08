<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 30 — billing:go-no-go (BIL-R015). Aggregates the billing governance
 * audit, the Sprint 24–29 prior-sprint gate contract, the Sprint 30 command/doc
 * contract, and the Android release readiness script into one GO/WATCH/NO-GO.
 * Never prints secrets, never deploys, never charges. Exit: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO_GO.
 */
class BillingGoNoGoCommand extends Command
{
    protected $signature = 'billing:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate billing governance audit + prior-sprint gates into a GO/WATCH/NO-GO decision.';

    public function handle(BillingGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Billing GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === BillingGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['decision'] === BillingGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
