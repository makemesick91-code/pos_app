<?php

namespace App\Console\Commands;

use App\Models\PerformanceBenchmarkRun;
use App\Services\Performance\PerformanceThresholdGateService;
use Illuminate\Console\Command;

class PerformanceThresholdCheckCommand extends Command
{
    protected $signature = 'performance:threshold-check {--run=} {--json}';
    protected $description = 'Evaluate Sprint 38 benchmark thresholds and fail closed on regression.';

    public function handle(PerformanceThresholdGateService $service): int
    {
        $run = $this->option('run') ? PerformanceBenchmarkRun::query()->findOrFail((int) $this->option('run')) : PerformanceBenchmarkRun::query()->latest()->firstOrFail();
        $result = $service->evaluate($run);
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $result['status'] === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
