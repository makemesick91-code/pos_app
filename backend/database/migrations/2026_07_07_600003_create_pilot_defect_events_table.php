<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation.
 *
 * Immutable, append-only lifecycle event trail for a pilot defect. Every
 * create/update/assign/status-change/severity-change/SLA-breach/accepted-risk/
 * fix/retest/verify/close appends one row here. Rows are never deleted or
 * updated, so a closed or verified defect always retains its full history.
 * Payloads must never store secrets.
 *
 * See docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_defect_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilot_defect_id')->constrained('pilot_defects')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type'); // CREATED | UPDATED | ASSIGNED | STATUS_CHANGED | ...
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('from_severity')->nullable();
            $table->string('to_severity')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->timestamps();

            $table->index('pilot_defect_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_defect_events');
    }
};
