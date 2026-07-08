<?php

namespace App\Console\Commands;

use App\Services\PaymentGateway\PaymentGatewayGoNoGoService;
use Illuminate\Console\Command;

/**
 * Sprint 31 — payment-gateway:go-no-go (PGW-R017). Aggregates the gateway
 * governance audit, the Sprint 31 command/doc contract, and the Sprint 24–30
 * prior-sprint gate contract into one GO/WATCH/NO-GO. Never prints secrets, never
 * deploys, never charges, never calls a real gateway. Exit: 0 — GO/WATCH (unless
 * --strict on WATCH), 1 — NO_GO.
 */
class PaymentGatewayGoNoGoCommand extends Command
{
    protected $signature = 'payment-gateway:go-no-go
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Aggregate payment gateway governance audit + prior-sprint gates into a GO/WATCH/NO-GO decision.';

    public function handle(PaymentGatewayGoNoGoService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Payment Gateway GO / WATCH / NO-GO');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === PaymentGatewayGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['decision'] === PaymentGatewayGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
