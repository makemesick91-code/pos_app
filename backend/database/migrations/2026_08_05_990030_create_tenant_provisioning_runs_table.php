<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 33 — Tenant Onboarding, Trial Activation & First-Branch Provisioning.
 *
 * Tracks the onboarding lifecycle for each tenant creation/provisioning attempt.
 * DELIBERATELY SEPARATE from the Sprint 12 `tenant_onboarding_runs` table (which
 * records the older platform-admin onboarding wizard + demo data). This table
 * governs the commercial provisioning chain: plan → trial → branch → users →
 * device/register setup → seed → trial-to-paid readiness.
 *
 * `idempotency_key` is unique so a replayed request resumes the existing run and
 * never creates a second tenant. `checklist_json`, `metadata_json` and
 * `failure_reason` are redacted/safe — they never store passwords, tokens,
 * signatures or PII. See docs/architecture/sprint-33-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_provisioning_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('requested_plan_code');
            $table->string('resolved_plan_code')->nullable();
            $table->string('onboarding_type')->default('platform_admin');
            $table->string('status')->default('draft');
            $table->string('idempotency_key')->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            // "first branch" maps to a Store in this codebase (there is no Branch model).
            $table->foreignId('first_branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('first_cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            // No dedicated register table exists; the first register is the first store.
            $table->unsignedBigInteger('first_register_id')->nullable();
            $table->foreignId('first_device_id')->nullable()->constrained('registered_devices')->nullOnDelete();
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_period')->nullable();
            $table->foreignId('tenant_billing_invoice_id')->nullable()->constrained('tenant_billing_invoices')->nullOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained('tenant_billing_payment_intents')->nullOnDelete();
            $table->json('checklist_json')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('requested_plan_code');
            $table->index('trial_ends_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provisioning_runs');
    }
};
