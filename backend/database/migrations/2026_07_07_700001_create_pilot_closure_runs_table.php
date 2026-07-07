<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 18 — Pilot Closure & Production Handover Foundation.
 *
 * A pilot closure run captures the final review of a pilot: the final defect
 * summary, the accepted-risk review, and the handover-readiness summary, plus a
 * closure checklist and evidence references. It carries a status and a
 * GO/WATCH/NO_GO decision. Summaries are aggregate (counts/decisions) only —
 * never raw sensitive data — and no secret, password, or gateway credential may
 * be stored on the row.
 *
 * See docs/sprints/sprint-18-pilot-closure-production-handover-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_closure_runs', function (Blueprint $table) {
            $table->id();
            $table->string('closure_reference')->unique();
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | APPROVED | BLOCKED | CLOSED
            $table->string('decision')->default('NO_GO'); // GO | WATCH | NO_GO
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->json('final_defect_summary')->nullable();
            $table->json('accepted_risk_summary')->nullable();
            $table->json('handover_readiness_summary')->nullable();
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
        Schema::dropIfExists('pilot_closure_runs');
    }
};
