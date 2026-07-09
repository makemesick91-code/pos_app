<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_benchmark_runs', function (Blueprint $table) {
            $table->id();
            $table->string('profile', 32);
            $table->string('status', 24)->default('pending');
            $table->string('benchmark_key')->unique();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('environment_name')->nullable();
            $table->string('git_commit', 80)->nullable();
            $table->string('go_tag')->nullable();
            $table->unsignedInteger('tenant_count')->default(0);
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('pos_transaction_count')->default(0);
            $table->unsignedInteger('android_sync_batch_count')->default(0);
            $table->unsignedInteger('android_sync_item_count')->default(0);
            $table->unsignedInteger('import_row_count')->default(0);
            $table->unsignedInteger('export_report_row_count')->default(0);
            $table->unsignedInteger('payment_webhook_event_count')->default(0);
            $table->unsignedInteger('queue_job_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('memory_peak_mb')->nullable();
            $table->unsignedInteger('query_count')->nullable();
            $table->string('threshold_status', 16)->default('warn');
            $table->string('failure_reason')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['profile', 'status']);
            $table->index('threshold_status');
            $table->index('started_at');
        });

        Schema::create('performance_benchmark_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_benchmark_run_id')->constrained('performance_benchmark_runs')->cascadeOnDelete();
            $table->string('step_key', 48);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('memory_peak_mb')->nullable();
            $table->unsignedInteger('query_count')->nullable();
            $table->unsignedInteger('rows_processed')->nullable();
            $table->unsignedInteger('records_created')->nullable();
            $table->unsignedInteger('records_updated')->nullable();
            $table->unsignedInteger('duplicate_count')->nullable();
            $table->unsignedInteger('error_count')->nullable();
            $table->string('threshold_status', 16)->default('warn');
            $table->string('reason_code')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['performance_benchmark_run_id', 'step_key'], 'perf_steps_run_step_unique');
            $table->index('status');
            $table->index('threshold_status');
        });

        Schema::create('performance_query_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_key')->unique();
            $table->string('area', 32);
            $table->string('status', 24)->default('observed');
            $table->string('table_name')->nullable();
            $table->string('index_name')->nullable();
            $table->string('query_pattern_safe')->nullable();
            $table->json('before_metric_json')->nullable();
            $table->json('after_metric_json')->nullable();
            $table->string('decision_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['area', 'status']);
            $table->index('table_name');
            $table->index('index_name');
        });

        Schema::create('performance_deploy_checks', function (Blueprint $table) {
            $table->id();
            $table->string('environment_name');
            $table->string('git_commit', 80);
            $table->string('go_tag')->nullable();
            $table->string('deploy_status', 24)->default('pending');
            $table->string('smoke_status', 24)->default('pending');
            $table->string('performance_status', 24)->default('pending');
            $table->string('backup_reference_hash')->nullable();
            $table->timestamp('deploy_started_at')->nullable();
            $table->timestamp('deploy_completed_at')->nullable();
            $table->timestamp('smoke_completed_at')->nullable();
            $table->timestamp('performance_completed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metrics_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['environment_name', 'deploy_status']);
            $table->index('git_commit');
            $table->index('go_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_deploy_checks');
        Schema::dropIfExists('performance_query_reviews');
        Schema::dropIfExists('performance_benchmark_steps');
        Schema::dropIfExists('performance_benchmark_runs');
    }
};
