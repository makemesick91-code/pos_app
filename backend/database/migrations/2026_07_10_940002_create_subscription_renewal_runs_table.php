<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription renewal run. A run evaluates existing
 * TenantSubscription records into candidates and summarizes readiness. A run
 * NEVER charges, NEVER renews a subscription, and NEVER suspends a tenant. No
 * secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_reference')->unique();
            $table->foreignId('policy_id')->nullable()->constrained('subscription_renewal_policies')->nullOnDelete();
            $table->string('status')->default('DRAFT'); // DRAFT | RUNNING | COMPLETED | FAILED_MANUAL_REVIEW | ARCHIVED
            $table->date('run_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('run_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_runs');
    }
};
