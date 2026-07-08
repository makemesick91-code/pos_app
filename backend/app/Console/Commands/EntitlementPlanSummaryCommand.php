<?php

namespace App\Console\Commands;

use App\Services\Entitlements\EntitlementSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 32 — entitlement:plan-summary. Prints the configured plan limits,
 * premium/export/report keys, and enforcement posture (ENT-R020: config only, no
 * tenant data, no secrets).
 */
class EntitlementPlanSummaryCommand extends Command
{
    protected $signature = 'entitlement:plan-summary {--json : Output JSON}';

    protected $description = 'Show the configured plan limits, feature/export/report keys, and enforcement posture.';

    public function handle(EntitlementSummaryService $summary): int
    {
        $data = $summary->planSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Entitlement Plan Summary');
        $this->line('Runtime enforcement: '.($data['runtime_enforcement_enabled'] ? 'ENABLED' : 'DISABLED'));
        $this->line('Fail closed on unknown plan: '.($data['fail_closed_on_unknown_plan'] ? 'yes' : 'no'));
        $this->line('Plans: '.implode(', ', $data['plan_keys']).' (default: '.$data['default_plan'].')');
        $this->line('Limits: '.implode(', ', array_keys($data['limits'])));
        $this->line('Premium features: '.implode(', ', $data['feature_keys']));
        $this->line('Export keys: '.implode(', ', $data['export_keys']));
        $this->line('Report keys: '.implode(', ', $data['report_keys']));

        return self::SUCCESS;
    }
}
