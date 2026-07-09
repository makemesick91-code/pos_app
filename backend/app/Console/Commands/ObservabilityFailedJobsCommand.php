<?php

namespace App\Console\Commands;

use App\Services\Observability\FailedJobDiagnosticsService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:failed-jobs. Shows redacted failed-job diagnostics
 * grouped by a safe job label. Never prints the raw payload, exception message,
 * or stack trace. --json.
 */
class ObservabilityFailedJobsCommand extends Command
{
    protected $signature = 'observability:failed-jobs {--json : Output JSON} {--limit=100 : Max rows to inspect}';

    protected $description = 'Show redacted failed-job diagnostics grouped by safe reason.';

    public function handle(FailedJobDiagnosticsService $diagnostics): int
    {
        $summary = $diagnostics->summary((int) $this->option('limit'));

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Failed jobs: '.$summary['total'].' (table_present='.($summary['table_present'] ? 'yes' : 'no').')');
            foreach ($summary['groups'] as $group) {
                $this->line("  {$group['count']}x {$group['job_label']}");
            }
        }

        return self::SUCCESS;
    }
}
