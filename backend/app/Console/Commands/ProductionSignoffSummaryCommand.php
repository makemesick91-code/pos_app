<?php

namespace App\Console\Commands;

use App\Models\ProductionHandoverPackage;
use App\Services\Handover\ProductionSignoffService;
use Illuminate\Console\Command;

/**
 * Sprint 18 — production:signoff-summary.
 *
 * Summarizes the sign-off decisions on the latest production handover package
 * (required roles, approved, rejected, approved-with-risk) into a GO/WATCH/NO_GO
 * decision. When no package exists, reports a pending (WATCH) contract state.
 * Read-only — never persists, never prints secrets. Exit code: 0 — GO/WATCH
 * (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class ProductionSignoffSummaryCommand extends Command
{
    protected $signature = 'production:signoff-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize production handover sign-offs into a GO/WATCH/NO_GO decision.';

    public function handle(ProductionSignoffService $service): int
    {
        $package = ProductionHandoverPackage::query()->latest('id')->first();

        if ($package === null) {
            $report = [
                'decision' => ProductionSignoffService::DECISION_WATCH,
                'required_roles' => array_map('strtoupper', (array) config('production_handover.required_signoff_roles', [])),
                'required_count' => count((array) config('production_handover.required_signoff_roles', [])),
                'approved' => 0,
                'rejected' => 0,
                'approved_with_risk' => 0,
                'missing_roles' => array_map('strtoupper', (array) config('production_handover.required_signoff_roles', [])),
                'total_signoffs' => 0,
                'note' => 'No production handover package recorded yet.',
            ];
        } else {
            $report = $service->summary($package);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Production Sign-off Summary');
            $this->line('Required roles: '.$report['required_count']);
            $this->line('Approved: '.$report['approved']);
            $this->line('Rejected: '.$report['rejected']);
            $this->line('Approved with risk: '.$report['approved_with_risk']);
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === ProductionSignoffService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === ProductionSignoffService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
