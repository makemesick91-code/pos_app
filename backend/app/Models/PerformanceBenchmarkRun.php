<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceBenchmarkRun extends Model
{
    protected $fillable = ['profile', 'status', 'benchmark_key', 'started_by_user_id', 'environment_name', 'git_commit', 'go_tag', 'tenant_count', 'product_count', 'pos_transaction_count', 'android_sync_batch_count', 'android_sync_item_count', 'import_row_count', 'export_report_row_count', 'payment_webhook_event_count', 'queue_job_count', 'duration_ms', 'memory_peak_mb', 'query_count', 'threshold_status', 'failure_reason', 'metrics_json', 'metadata_json', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['metrics_json' => 'array', 'metadata_json' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(PerformanceBenchmarkStep::class);
    }
}
