<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 36 — vendor-neutral alert / incident suggestion records (OBS-R018/R021).
 *
 * A suggestion is derived from an anomaly event. It never mutates tenant state.
 * Accepting a suggestion may create a Sprint 35 support incident (through
 * SupportIncidentService, audited); support_incident_id links it. Dismissing is
 * audited. summary_safe/metadata_json are redacted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observability_alert_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('anomaly_event_id')->nullable()->constrained('observability_anomaly_events')->nullOnDelete();
            $table->string('suggested_action');
            $table->string('severity')->default('low');
            $table->string('status')->default('suggested');
            $table->unsignedBigInteger('support_incident_id')->nullable();
            $table->string('reason_code')->nullable();
            $table->string('summary_safe')->nullable();
            $table->json('metadata_json')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'severity'], 'obs_alert_status_sev_idx');
            $table->index('tenant_id', 'obs_alert_tenant_idx');
            $table->index('anomaly_event_id', 'obs_alert_anomaly_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observability_alert_suggestions');
    }
};
