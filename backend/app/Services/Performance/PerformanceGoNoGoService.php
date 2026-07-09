<?php

namespace App\Services\Performance;

use App\Models\PerformanceBenchmarkRun;

class PerformanceGoNoGoService
{
    public function __construct(private readonly PerformanceGovernanceAuditService $governance) {}

    public function evaluate(bool $requireDeploy = false): array
    {
        $signals = $this->governance->evaluate();
        $latest = PerformanceBenchmarkRun::query()->latest()->first();
        $signals[] = ['key' => 'smoke_performance_run', 'status' => $latest && $latest->threshold_status === 'pass' ? 'PASS' : 'FAIL', 'message' => $latest ? "Latest run {$latest->id} is {$latest->threshold_status}." : 'No performance run recorded.'];
        $deployOk = ! $requireDeploy || \App\Models\PerformanceDeployCheck::query()->where('deploy_status', 'deployed')->where('smoke_status', 'passed')->where('performance_status', 'passed')->exists();
        $signals[] = ['key' => 'deploy_evidence', 'status' => $deployOk ? 'PASS' : 'FAIL', 'message' => $deployOk ? 'Deploy evidence requirement satisfied for this mode.' : 'Pilot/VPS deploy evidence missing.'];
        $decision = collect($signals)->contains(fn ($signal) => $signal['status'] === 'FAIL') ? 'NO_GO' : 'GO';
        return ['decision' => $decision, 'signals' => $signals];
    }
}
