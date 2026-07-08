<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription renewal policy. Governance only: it controls the
 * renewal window, grace period, dunning start window, and manual approval
 * requirement. A policy NEVER triggers real sending, NEVER auto-charges, and
 * NEVER auto-suspends a tenant. No secrets are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_reference')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE | INACTIVE | ARCHIVED
            $table->integer('renewal_window_days')->default(14);
            $table->integer('grace_period_days')->default(7);
            $table->integer('dunning_start_days_before_expiry')->default(7);
            $table->integer('max_manual_dunning_notices')->default(3);
            $table->boolean('requires_manual_approval')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_policies');
    }
};
