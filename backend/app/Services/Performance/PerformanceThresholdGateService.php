<?php

namespace App\Services\Performance;

use App\Models\PerformanceBenchmarkRun;

class PerformanceThresholdGateService
{
    public function evaluate(PerformanceBenchmarkRun $run): array
    {
        $thresholds = (array) config("performance_governance.profiles.{$run->profile}.thresholds", []);
        $failures = [];
        if ($run->duration_ms !== null && $run->duration_ms > (int) ($thresholds['max_runtime_ms'] ?? PHP_INT_MAX)) {
            $failures[] = 'runtime_exceeded';
        }
        if ($run->query_count !== null && $run->query_count > (int) ($thresholds['max_average_query_count'] ?? PHP_INT_MAX)) {
            $failures[] = 'query_count_exceeded';
        }
        if ($run->memory_peak_mb !== null && $run->memory_peak_mb > (int) ($thresholds['max_memory_mb'] ?? PHP_INT_MAX)) {
            $failures[] = 'memory_exceeded';
        }
        $failedSteps = $run->steps()->where('threshold_status', 'fail')->pluck('reason_code')->filter()->values()->all();
        $failures = array_values(array_unique(array_merge($failures, $failedSteps)));
        $status = $failures === [] ? 'pass' : 'fail';
        $run->forceFill([
            'threshold_status' => $status,
            'failure_reason' => $status === 'fail' ? implode(',', $failures) : null,
        ])->save();
        return ['status' => $status, 'reason_codes' => $failures, 'profile' => $run->profile];
    }
}
