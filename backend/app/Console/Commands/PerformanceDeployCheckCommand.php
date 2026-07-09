<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceDeployGateService;
use Illuminate\Console\Command;

class PerformanceDeployCheckCommand extends Command
{
    protected $signature = 'performance:deploy-check {--environment=pilot_vps} {--git-commit=} {--go-tag=} {--backup-reference=} {--confirm-deployed} {--json}';
    protected $description = 'Record Sprint 38 pilot/VPS deploy performance gate evidence.';

    public function handle(PerformanceDeployGateService $service): int
    {
        $check = $service->record([
            'environment_name' => $this->option('environment'),
            'git_commit' => $this->option('git-commit'),
            'go_tag' => $this->option('go-tag'),
            'backup_reference' => $this->option('backup-reference'),
        ], (bool) $this->option('confirm-deployed'));
        $this->line(json_encode(['id' => $check->id, 'deploy_status' => $check->deploy_status, 'smoke_status' => $check->smoke_status, 'performance_status' => $check->performance_status, 'failure_reason' => $check->failure_reason], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $check->deploy_status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
