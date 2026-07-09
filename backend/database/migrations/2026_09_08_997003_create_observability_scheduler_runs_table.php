<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 36 — scheduler/command heartbeat + staleness tracking (OBS-R011).
 *
 * Records a run per scheduled/console command so SchedulerHealthService can
 * detect stale/missed/long-running commands from config thresholds. failure_reason
 * is redacted; no secrets/PII are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observability_scheduler_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command_name');
            $table->string('status')->default('started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['command_name', 'started_at'], 'obs_scheduler_cmd_started_idx');
            $table->index('status', 'obs_scheduler_status_idx');
            $table->index('completed_at', 'obs_scheduler_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_scheduler_runs');
    }
};
