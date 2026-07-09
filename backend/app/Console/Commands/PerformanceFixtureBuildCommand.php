<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceFixtureService;
use Illuminate\Console\Command;

class PerformanceFixtureBuildCommand extends Command
{
    protected $signature = 'performance:fixture-build {--profile=ci_smoke} {--execute} {--json}';
    protected $description = 'Build deterministic Sprint 38 benchmark fixtures; dry-run by default.';

    public function handle(PerformanceFixtureService $service): int
    {
        $result = $service->build((string) $this->option('profile'), (bool) $this->option('execute'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
