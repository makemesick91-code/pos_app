<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.
 *
 * Persistent production incident register. Each incident carries a severity
 * (P0–P4), a lifecycle status, an impacted area, optional tenant/store context
 * (some incidents are global), an SLA due timestamp, an optional SLA breach
 * timestamp, owner/reporter, and an evidence reference. Accepted-risk governance
 * fields let a blocking incident be explicitly accepted with an approver, reason,
 * and expiry — the original severity is never hidden.
 *
 * Open P0/P1 incidents force NO-GO unless a valid accepted risk exists; open P2
 * forces WATCH. No secret, password, or gateway credential may be stored in
 * description/metadata — the service sanitises secret-like values.
 *
 * See docs/sprints/sprint-19-production-operations-post-handover-governance-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_reference')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('area'); // BACKEND_API | ANDROID_APP | AUTH | ...
            $table->string('severity'); // P0 | P1 | P2 | P3 | P4
            $table->string('status')->default('OPEN'); // OPEN | ACKNOWLEDGED | INVESTIGATING | MITIGATED | RESOLVED | CLOSED | ACCEPTED_RISK
            $table->string('impact'); // free-form impact classification
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('sla_breached_at')->nullable();
            $table->timestamp('accepted_risk_at')->nullable();
            $table->foreignId('accepted_risk_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('accepted_risk_reason')->nullable();
            $table->timestamp('accepted_risk_expires_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('severity');
            $table->index('status');
            $table->index('area');
            $table->index('tenant_id');
            $table->index('sla_due_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_incidents');
    }
};
