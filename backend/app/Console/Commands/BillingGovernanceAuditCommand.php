<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 30 — billing:governance-audit. Read-only. Non-zero exit if a critical
 * billing governance defect is found (missing table/service, missing pricing,
 * duplicate invoice, negative amount, invoice without plan, invalid payment
 * amount, a non-admin mutation route, a flipped guardrail, or a missing rule).
 */
class BillingGovernanceAuditCommand extends Command
{
    protected $signature = 'billing:governance-audit
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Audit the billing invoice/payment governance foundation (BIL-R001..R014).';

    public function handle(BillingGovernanceAuditService $service): int
    {
        $report = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Billing Governance Audit');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === BillingGovernanceAuditService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $report['decision'] === BillingGovernanceAuditService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
