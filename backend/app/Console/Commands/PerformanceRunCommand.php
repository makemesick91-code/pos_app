<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceBenchmarkService;
use Illuminate\Console\Command;

class PerformanceRunCommand extends Command
{
    protected $signature = 'performance:run {--profile=ci_smoke} {--json}';
    protected $description = 'Run a deterministic Sprint 38 performance benchmark profile.';

    public function handle(PerformanceBenchmarkService $service): int
    {
        $run = $service->run((string) $this->option('profile'));
        $payload = ['id' => $run->id, 'profile' => $run->profile, 'status' => $run->status, 'threshold_status' => $run->threshold_status, 'duration_ms' => $run->duration_ms];
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $run->threshold_status === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
