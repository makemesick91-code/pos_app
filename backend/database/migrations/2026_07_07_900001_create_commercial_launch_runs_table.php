<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20 — Commercial Launch Readiness & SaaS Packaging Foundation.
 *
 * A persistent commercial launch run: the evidence-backed record of a
 * commercial launch readiness review. It captures the aggregate package,
 * pricing, sales-enablement, onboarding-capacity, risk and signoff summaries,
 * evidence references, and the GO/WATCH/NO_GO decision, plus append-only actor
 * metadata.
 *
 * Summaries are aggregate only — never raw secrets, payment gateway secrets,
 * server credentials, or private customer data. Recording a run never deploys,
 * never bills a real customer, never opens public signup, and never sends real
 * alerts.
 *
 * See docs/sprints/sprint-20-commercial-launch-readiness-saas-packaging-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_launch_runs', function (Blueprint $table) {
            $table->id();
            $table->string('launch_reference')->unique();
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | READY | WATCH | BLOCKED | LAUNCHED | CLOSED
            $table->string('decision')->nullable(); // GO | WATCH | NO_GO
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->json('package_summary')->nullable();
            $table->json('pricing_summary')->nullable();
            $table->json('sales_enablement_summary')->nullable();
            $table->json('onboarding_capacity_summary')->nullable();
            $table->json('risk_summary')->nullable();
            $table->json('signoff_summary')->nullable();
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
        Schema::dropIfExists('commercial_launch_runs');
    }
};
