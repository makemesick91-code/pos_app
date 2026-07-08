<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription renewal decision. A decision records governance. It
 * NEVER updates a TenantSubscription automatically; the only mutation path is the
 * explicit, platform-admin-only, audit-logged applyManualRenewalDecision action.
 * Payment evidence NEVER auto-renews a subscription. No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_reference')->unique();
            $table->foreignId('candidate_id')->constrained('subscription_renewal_candidates')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('decision'); // APPROVE_MANUAL_RENEWAL | APPROVE_WITH_RISK | REJECT_RENEWAL | DEFER_REVIEW | DO_NOT_RENEW
            $table->string('status')->default('DRAFT'); // DRAFT | RECORDED | APPLIED_MANUALLY | VOIDED
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->foreignId('approved_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('manual_billing_invoice_id')->nullable()->constrained('saas_billing_invoices')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('decision');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_decisions');
    }
};
