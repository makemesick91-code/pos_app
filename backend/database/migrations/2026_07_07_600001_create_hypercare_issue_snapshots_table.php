<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation.
 *
 * Optional persistence for a hypercare issue triage snapshot (the deferred
 * persistence noted in Sprint 16). Stores a decision summary and aggregated
 * counts only — no raw private customer data and no secrets. `snapshot_reference`
 * is unique so a replay reuses the existing snapshot.
 *
 * See docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hypercare_issue_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_reference')->unique();
            $table->string('decision')->default('GO'); // GO | WATCH | NO-GO
            $table->json('issue_counts')->nullable();
            $table->unsignedInteger('blocking_issue_count')->default(0);
            $table->unsignedInteger('major_issue_count')->default(0);
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('decision');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hypercare_issue_snapshots');
    }
};
