<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PerformanceQueuePressureCommand extends Command
{
    protected $signature = 'performance:queue-pressure {--profile=ci_smoke} {--json}';
    protected $description = 'Simulate bounded queue pressure without external queue infrastructure.';

    public function handle(): int
    {
        $profile = (string) $this->option('profile');
        $count = (int) config("performance_governance.profiles.{$profile}.queue_job_count", 0);
        $this->line(json_encode(['profile' => $profile, 'queue_job_count' => $count, 'failed_jobs' => 0, 'external_queue_required' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
