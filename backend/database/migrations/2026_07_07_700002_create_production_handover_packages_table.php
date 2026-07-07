<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 18 — Pilot Closure & Production Handover Foundation.
 *
 * A production handover package bundles the release readiness, operator/admin
 * handover, support/SLA handover, backup/restore handover, and release ownership
 * matrix into a single reviewable, sign-off-driven artifact. candidate_commit /
 * candidate_tag are references only — never deployment credentials. Status
 * changes are conservative and never delete previous evidence. No secret, server
 * credential, or gateway key may be stored on the row.
 *
 * See docs/sprints/sprint-18-pilot-closure-production-handover-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_handover_packages', function (Blueprint $table) {
            $table->id();
            $table->string('handover_reference')->unique();
            $table->foreignId('pilot_closure_run_id')->nullable()->constrained('pilot_closure_runs')->nullOnDelete();
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | READY | WATCH | BLOCKED | HANDED_OVER
            $table->string('decision')->default('NO_GO'); // GO | WATCH | NO_GO
            $table->string('candidate_commit')->nullable();
            $table->string('candidate_tag')->nullable();
            $table->json('production_readiness_summary')->nullable();
            $table->json('operator_handover_summary')->nullable();
            $table->json('admin_handover_summary')->nullable();
            $table->json('support_sla_summary')->nullable();
            $table->json('backup_restore_summary')->nullable();
            $table->json('ownership_matrix')->nullable();
            $table->json('checklist')->nullable();
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
        Schema::dropIfExists('production_handover_packages');
    }
};
