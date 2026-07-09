<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceQueryReview extends Model
{
    protected $fillable = ['review_key', 'area', 'status', 'table_name', 'index_name', 'query_pattern_safe', 'before_metric_json', 'after_metric_json', 'decision_reason', 'metadata_json'];

    protected function casts(): array
    {
        return ['before_metric_json' => 'array', 'after_metric_json' => 'array', 'metadata_json' => 'array'];
    }
}
