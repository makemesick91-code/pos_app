<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation.
 *
 * Persistent pilot defect register. Each defect carries severity, status, a
 * blocking flag, owner/reporter, functional area, optional tenant/store context
 * (some defects are global), an SLA due timestamp, and an evidence reference.
 * Accepted-risk governance fields never hide the original severity. Fix
 * verification/retest fields record who verified and the result. Lifecycle
 * changes are recorded immutably in pilot_defect_events (never deleted).
 *
 * No secret, password, or payment gateway credential may be stored in
 * description/metadata/environment — the service sanitises secret-like values.
 *
 * See docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_defects', function (Blueprint $table) {
            $table->id();
            $table->string('defect_reference')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('area'); // AUTH | SYNC | CASHIER | PAYMENT_QRIS | ...
            $table->string('severity'); // BLOCKER | CRITICAL | MAJOR | MINOR | TRIVIAL
            $table->string('status')->default('OPEN'); // OPEN | IN_PROGRESS | FIXED | RETEST | VERIFIED | CLOSED | ACCEPTED_RISK
            $table->boolean('blocking')->default(false);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('steps_to_reproduce')->nullable();
            $table->text('expected_result')->nullable();
            $table->text('actual_result')->nullable();
            $table->json('environment')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('sla_breached_at')->nullable();
            $table->timestamp('accepted_risk_at')->nullable();
            $table->foreignId('accepted_risk_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('accepted_risk_reason')->nullable();
            $table->timestamp('accepted_risk_expires_at')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_result')->nullable(); // PASS | FAIL
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('severity');
            $table->index('status');
            $table->index('area');
            $table->index('blocking');
            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_defects');
    }
};
