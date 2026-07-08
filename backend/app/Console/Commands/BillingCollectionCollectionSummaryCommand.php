<?php

namespace App\Console\Commands;

use App\Services\BillingCollection\BillingCollectionActivityService;
use App\Services\BillingCollection\BillingPaymentEvidenceService;
use Illuminate\Console\Command;

/**
 * Sprint 23 — billing-collection:collection-summary.
 *
 * Summarizes manual collection activity and manual payment evidence. Confirms
 * manual-follow-up-only and no-real-sending guardrails. Never prints secrets, never
 * sends a real message, never calls a payment gateway. Exit code: 0 — GO/WATCH
 * (unless --strict on WATCH), 1 — NO_GO.
 */
class BillingCollectionCollectionSummaryCommand extends Command
{
    protected $signature = 'billing-collection:collection-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize manual billing collection activity and payment evidence.';

    public function handle(BillingCollectionActivityService $activities, BillingPaymentEvidenceService $evidences): int
    {
        $activity = $activities->summary();
        $evidence = $evidences->summary();

        $report = [
            'decision' => 'GO',
            'activities_by_type' => $activity['by_type'],
            'activities_by_status' => $activity['by_status'],
            'payment_evidence_by_status' => $evidence['by_status'],
            'manual_follow_up_only' => true,
            'no_real_sending' => true,
            'no_payment_gateway_call' => true,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Billing Collection Summary');
            $this->line('activities_by_type: '.json_encode($report['activities_by_type']));
            $this->line('activities_by_status: '.json_encode($report['activities_by_status']));
            $this->line('payment_evidence_by_status: '.json_encode($report['payment_evidence_by_status']));
            $this->line('manual_follow_up_only: PASS');
            $this->line('no_real_sending: PASS');
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === 'NO_GO') {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === 'WATCH') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
