<?php

namespace App\Console\Commands;

use App\Services\ExportGovernance\ExportGovernanceCoverageService;
use Illuminate\Console\Command;

/**
 * Sprint 29 — export-governance:coverage-summary. Read-only redacted counts of
 * discovered / registered / metered / exempt export routes and any gaps
 * (EGC-R010). Never prints secrets, never mutates.
 */
class ExportGovernanceCoverageSummaryCommand extends Command
{
    protected $signature = 'export-governance:coverage-summary {--json : Output JSON}';

    protected $description = 'Summarize export governance coverage: discovered, registered, metered, exempt, gaps.';

    public function handle(ExportGovernanceCoverageService $coverage): int
    {
        $summary = $coverage->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Export Governance Coverage Summary');
        $this->line('Meter key: '.$summary['meter_key'].' (meterable: '.($summary['meterable'] ? 'true' : 'false').')');
        foreach ($summary['totals'] as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        $this->line('Metered routes:');
        foreach ($summary['metered_routes'] as $r) {
            $this->line("  - {$r['signature']} ({$r['report_type']}/{$r['format']})");
        }
        $this->line('Exempt routes:');
        foreach ($summary['exempt_routes'] as $r) {
            $this->line("  - {$r['signature']} — {$r['exempt_reason']}");
        }
        if ($summary['gaps'] !== []) {
            $this->line('Gaps:');
            foreach ($summary['gaps'] as $gap) {
                $this->line("  ! {$gap}");
            }
        }

        return self::SUCCESS;
    }
}
