<?php

namespace App\Console\Commands;

use App\Services\Observability\ObservabilityHealthService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:health. Prints the deterministic application health
 * overview (infrastructure + queue + scheduler). Supports --json. No secrets/PII.
 */
class ObservabilityHealthCommand extends Command
{
    protected $signature = 'observability:health {--json : Output JSON}';

    protected $description = 'Show the application health overview (infrastructure + queue + scheduler).';

    public function handle(ObservabilityHealthService $health): int
    {
        $overview = $health->overview();

        if ($this->option('json')) {
            $this->line((string) json_encode($overview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Application health: '.$overview['status']);
        $this->line('Reasons: '.implode(', ', $overview['reason_codes']));
        $this->line('Infrastructure: '.($overview['components']['infrastructure']['status'] ?? 'unknown'));
        $this->line('Queue: '.($overview['components']['queue']['status'] ?? 'unknown'));
        $this->line('Scheduler: '.($overview['components']['scheduler']['status'] ?? 'unknown'));

        return self::SUCCESS;
    }
}
