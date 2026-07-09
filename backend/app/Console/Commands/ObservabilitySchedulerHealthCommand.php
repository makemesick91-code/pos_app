<?php

namespace App\Console\Commands;

use App\Services\Observability\SchedulerHealthService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:scheduler-health. Shows scheduler/command freshness
 * and staleness from recorded runs. Failure reasons are redacted upstream. --json.
 */
class ObservabilitySchedulerHealthCommand extends Command
{
    protected $signature = 'observability:scheduler-health {--json : Output JSON}';

    protected $description = 'Show scheduler freshness/staleness from recorded command runs.';

    public function handle(SchedulerHealthService $scheduler): int
    {
        $summary = $scheduler->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Scheduler health: '.$summary['status']);
            $this->line('Reasons: '.implode(', ', $summary['reason_codes']));
            foreach ($summary['commands'] as $c) {
                $this->line("  [{$c['command_health']}] {$c['command_name']} (last: {$c['last_status']})");
            }
        }

        return self::SUCCESS;
    }
}
