<?php

namespace App\Console\Commands;

use App\Services\Observability\QueueHealthService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:queue-health. Shows queue backlog / oldest-pending
 * age / failed-job counts. Aggregate counts only, no raw job payloads. --json.
 */
class ObservabilityQueueHealthCommand extends Command
{
    protected $signature = 'observability:queue-health {--json : Output JSON}';

    protected $description = 'Show queue/failed-job health (aggregate counts only, no payloads).';

    public function handle(QueueHealthService $queue): int
    {
        $summary = $queue->summary();

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Queue health: '.$summary['status']);
            $this->line('Reasons: '.implode(', ', $summary['reason_codes']));
            foreach ($summary['metrics'] as $k => $v) {
                $this->line("  {$k}: ".(is_bool($v) ? ($v ? 'true' : 'false') : $v));
            }
        }

        return self::SUCCESS;
    }
}
