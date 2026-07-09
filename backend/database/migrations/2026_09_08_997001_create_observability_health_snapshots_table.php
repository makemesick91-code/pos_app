<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 36 — safe, aggregated health snapshots (OBS-R003/R004/R020).
 *
 * Stores only aggregate, redacted metrics and a deterministic status +
 * reason_code per scope. Never stores raw payloads, credentials, or PII.
 * See docs/architecture/sprint-36-observability-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observability_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type')->default('application');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('status')->default('healthy');
            $table->string('reason_code')->nullable();
            $table->string('summary_safe')->nullable();
            $table->json('metrics_json')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'status'], 'obs_snapshots_scope_status_idx');
            $table->index(['tenant_id', 'checked_at'], 'obs_snapshots_tenant_checked_idx');
            $table->index('checked_at', 'obs_snapshots_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_health_snapshots');
    }
};
