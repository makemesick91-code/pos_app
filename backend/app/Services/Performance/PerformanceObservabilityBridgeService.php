<?php

namespace App\Services\Performance;

use App\Models\PerformanceBenchmarkRun;

class PerformanceObservabilityBridgeService
{
    public function snapshot(PerformanceBenchmarkRun $run): array
    {
        return [
            'status' => 'recorded',
            'run_id' => $run->id,
            'profile' => $run->profile,
            'metrics' => ['duration_ms' => $run->duration_ms, 'memory_peak_mb' => $run->memory_peak_mb, 'threshold_status' => $run->threshold_status],
            'redacted' => true,
        ];
    }
}
