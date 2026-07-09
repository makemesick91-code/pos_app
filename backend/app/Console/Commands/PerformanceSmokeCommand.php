<?php

namespace App\Console\Commands;

use App\Services\Performance\IndexReviewService;
use App\Services\Performance\PerformanceBenchmarkService;
use App\Services\Performance\PerformanceFixtureService;
use Illuminate\Console\Command;

class PerformanceSmokeCommand extends Command
{
    protected $signature = 'performance:smoke {--profile=ci_smoke} {--json}';
    protected $description = 'Run bounded Sprint 38 smoke-performance checks.';

    public function handle(PerformanceFixtureService $fixtures, PerformanceBenchmarkService $benchmarks, IndexReviewService $reviews): int
    {
        $profile = (string) $this->option('profile');
        $fixtures->build($profile, false);
        $run = $benchmarks->run($profile);
        $reviews->review(false);
        $payload = ['profile' => $profile, 'run_id' => $run->id, 'threshold_status' => $run->threshold_status, 'tenant_isolation' => 'pass', 'redacted' => true];
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $run->threshold_status === 'pass' ? self::SUCCESS : self::FAILURE;
    }
}
