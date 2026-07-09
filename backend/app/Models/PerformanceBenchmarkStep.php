<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceBenchmarkStep extends Model
{
    protected $fillable = ['performance_benchmark_run_id', 'step_key', 'status', 'duration_ms', 'memory_peak_mb', 'query_count', 'rows_processed', 'records_created', 'records_updated', 'duplicate_count', 'error_count', 'threshold_status', 'reason_code', 'metrics_json', 'metadata_json', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['metrics_json' => 'array', 'metadata_json' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PerformanceBenchmarkRun::class, 'performance_benchmark_run_id');
    }
}
