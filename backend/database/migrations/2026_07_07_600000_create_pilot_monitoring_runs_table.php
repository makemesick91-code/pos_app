<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation.
 *
 * Optional persistence for a pilot monitoring run snapshot (the deferred
 * persistence noted in Sprint 16). A run is only written when a monitoring
 * evaluation is explicitly persisted; the daily monitoring command remains
 * read-only by default. `run_reference` is unique so a replay reuses the
 * existing snapshot. `signals`/`summary` store a decision summary only — never
 * secrets and never raw customer data.
 *
 * See docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_monitoring_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_reference')->unique();
            $table->string('status')->default('RECORDED');
            $table->string('decision')->default('GO'); // GO | WATCH | NO-GO
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->json('signals')->nullable();
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
        Schema::dropIfExists('pilot_monitoring_runs');
    }
};
