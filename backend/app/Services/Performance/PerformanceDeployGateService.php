<?php

namespace App\Services\Performance;

use App\Models\PerformanceDeployCheck;

class PerformanceDeployGateService
{
    public function record(array $data, bool $confirmDeployed = false): PerformanceDeployCheck
    {
        $status = $confirmDeployed ? 'deployed' : 'pending';
        return PerformanceDeployCheck::query()->create([
            'environment_name' => $data['environment_name'] ?? app()->environment(),
            'git_commit' => $data['git_commit'] ?? trim((string) @shell_exec('git rev-parse --short=12 HEAD 2>/dev/null')) ?: 'unknown',
            'go_tag' => $data['go_tag'] ?? null,
            'deploy_status' => $status,
            'smoke_status' => $confirmDeployed ? 'passed' : 'pending',
            'performance_status' => $confirmDeployed ? 'passed' : 'pending',
            'backup_reference_hash' => isset($data['backup_reference']) ? hash('sha256', (string) $data['backup_reference']) : null,
            'deploy_started_at' => now(),
            'deploy_completed_at' => $confirmDeployed ? now() : null,
            'smoke_completed_at' => $confirmDeployed ? now() : null,
            'performance_completed_at' => $confirmDeployed ? now() : null,
            'failure_reason' => $confirmDeployed ? null : 'deploy_confirmation_required',
            'metrics_json' => ['redacted' => true],
            'metadata_json' => ['deploy_gate_policy' => config('performance_governance.deploy_gate_policy')],
        ]);
    }
}
