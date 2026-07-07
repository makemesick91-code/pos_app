<?php

namespace App\Console\Commands;

use App\Models\SaasPackageCatalog;
use App\Services\Commercial\SaaSPackageCatalogService;
use Illuminate\Console\Command;

/**
 * Sprint 20 — commercial:package-summary.
 *
 * Summarizes the SaaS package catalog (active/draft/blocked counts, segment
 * coverage) into a GO/WATCH/NO-GO decision. Pricing is governance metadata only;
 * this command starts no real billing. Never prints secrets. Exit code: 0 —
 * GO/WATCH (unless --strict on WATCH), 1 — NO_GO / strict WATCH.
 */
class CommercialPackageSummaryCommand extends Command
{
    protected $signature = 'commercial:package-summary
        {--json : Output JSON}
        {--strict : Fail on warnings}';

    protected $description = 'Summarize the SaaS package catalog into a GO/WATCH/NO-GO decision.';

    public function handle(SaaSPackageCatalogService $service): int
    {
        $report = $service->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $counts = $report['counts'];
            $this->line('SaaS Package Summary');
            $this->line('Active packages: '.($counts[SaasPackageCatalog::STATUS_ACTIVE] ?? 0));
            $this->line('Draft packages: '.($counts[SaasPackageCatalog::STATUS_DRAFT] ?? 0));
            $this->line('Blocked packages: '.($counts[SaasPackageCatalog::STATUS_BLOCKED] ?? 0));
            $this->line('Decision: '.$report['decision']);
        }

        return $this->exitCode((string) $report['decision']);
    }

    private function exitCode(string $decision): int
    {
        if ($decision === SaaSPackageCatalogService::DECISION_NO_GO) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $decision === SaaSPackageCatalogService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
