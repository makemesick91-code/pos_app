<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceGoNoGoService;
use Illuminate\Console\Command;

class PerformanceGoNoGoCommand extends Command
{
    protected $signature = 'performance:go-no-go {--json} {--strict} {--require-deploy}';
    protected $description = 'Aggregate Sprint 38 performance GO/NO-GO checks.';

    public function handle(PerformanceGoNoGoService $service): int
    {
        $report = $service->evaluate((bool) $this->option('require-deploy'));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $report['decision'] === 'NO_GO' ? self::FAILURE : self::SUCCESS;
    }
}
