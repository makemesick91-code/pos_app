<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 36 — detected observability anomalies (OBS-R013..R019).
 *
 * Read-only detection output only: each row is a safe, redacted descriptor of an
 * anomaly detected from a trusted Sprint 30–35 ledger. Duplicate anomalies (same
 * tenant + anomaly_key) update occurrence_count/last_seen_at instead of inserting
 * a new row (OBS-R029). No domain state is ever mutated by recording an anomaly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observability_anomaly_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('anomaly_key');
            $table->string('category')->default('other');
            $table->string('severity')->default('low');
            $table->string('status')->default('open');
            $table->string('reason_code')->nullable();
            $table->string('related_subject_type')->nullable();
            $table->unsignedBigInteger('related_subject_id')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('summary_safe')->nullable();
            $table->json('metadata_json')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'obs_anomaly_tenant_status_idx');
            $table->index(['category', 'severity'], 'obs_anomaly_cat_sev_idx');
            $table->index('anomaly_key', 'obs_anomaly_key_idx');
            $table->index('first_seen_at', 'obs_anomaly_first_seen_idx');
            $table->index('last_seen_at', 'obs_anomaly_last_seen_idx');
            $table->unique(['tenant_id', 'anomaly_key'], 'obs_anomaly_tenant_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_anomaly_events');
    }
};
