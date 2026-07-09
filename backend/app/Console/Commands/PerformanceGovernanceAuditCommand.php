<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceGovernanceAuditService;
use Illuminate\Console\Command;

class PerformanceGovernanceAuditCommand extends Command
{
    protected $signature = 'performance:governance-audit {--json}';
    protected $description = 'Evaluate Sprint 38 performance governance wiring.';

    public function handle(PerformanceGovernanceAuditService $service): int
    {
        $signals = $service->evaluate();
        $this->line(json_encode(['signals' => $signals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return collect($signals)->contains(fn ($signal) => $signal['status'] === 'FAIL') ? self::FAILURE : self::SUCCESS;
    }
}
