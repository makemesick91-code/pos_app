<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.
 *
 * Persistent production maintenance window register. Each window carries a
 * lifecycle status, a risk level (LOW/MEDIUM/HIGH/CRITICAL), scheduled and actual
 * start/end timestamps, an owner, an optional rollback plan reference, and an
 * evidence reference. A HIGH/CRITICAL window without a rollback plan reference
 * forces WATCH/NO-GO.
 *
 * A maintenance window record never performs a deployment and never stores
 * credentials. See docs/sprints/sprint-19-production-operations-post-handover-governance-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_reference')->unique();
            $table->string('status')->default('PLANNED'); // PLANNED | APPROVED | IN_PROGRESS | COMPLETED | CANCELLED | BLOCKED
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at');
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->string('risk_level')->default('LOW'); // LOW | MEDIUM | HIGH | CRITICAL
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rollback_plan_reference')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('risk_level');
            $table->index('scheduled_start_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_maintenance_windows');
    }
};
