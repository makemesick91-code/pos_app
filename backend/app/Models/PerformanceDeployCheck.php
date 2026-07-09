<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceDeployCheck extends Model
{
    protected $fillable = ['environment_name', 'git_commit', 'go_tag', 'deploy_status', 'smoke_status', 'performance_status', 'backup_reference_hash', 'deploy_started_at', 'deploy_completed_at', 'smoke_completed_at', 'performance_completed_at', 'failure_reason', 'metrics_json', 'metadata_json'];

    protected function casts(): array
    {
        return ['deploy_started_at' => 'datetime', 'deploy_completed_at' => 'datetime', 'smoke_completed_at' => 'datetime', 'performance_completed_at' => 'datetime', 'metrics_json' => 'array', 'metadata_json' => 'array'];
    }
}
