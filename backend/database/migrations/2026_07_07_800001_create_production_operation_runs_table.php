<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.
 *
 * A persistent production operation run: the evidence-backed record of a
 * post-handover operations review. It captures the aggregate health signals,
 * incident/backup/SLA/maintenance/release-rollback summaries, evidence
 * references, and the GO/WATCH/NO_GO decision, plus append-only actor metadata.
 *
 * Summaries are aggregate only — never raw secrets, server credentials, or
 * private customer data. Recording a run never deploys, never runs real
 * backup/restore, and never sends real alerts.
 *
 * See docs/sprints/sprint-19-production-operations-post-handover-governance-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_operation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_reference')->unique();
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | HEALTHY | WATCH | BLOCKED | CLOSED
            $table->string('decision')->nullable(); // GO | WATCH | NO_GO
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->json('health_signals')->nullable();
            $table->json('incident_summary')->nullable();
            $table->json('backup_restore_summary')->nullable();
            $table->json('support_sla_summary')->nullable();
            $table->json('maintenance_summary')->nullable();
            $table->json('release_rollback_summary')->nullable();
            $table->json('evidence_references')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('decision');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_operation_runs');
    }
};
