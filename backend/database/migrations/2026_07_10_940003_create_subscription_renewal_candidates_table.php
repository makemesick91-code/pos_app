<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription renewal candidate. A candidate MAY reference a
 * TenantSubscription and a SaaS billing invoice/account as read-only awareness.
 * Creating a candidate NEVER renews a subscription and a stage change NEVER
 * suspends a tenant. READY_FOR_MANUAL_RENEWAL means an admin decision is needed,
 * not an automatic renewal. No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_reference')->unique();
            $table->foreignId('run_id')->nullable()->constrained('subscription_renewal_runs')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->foreignId('policy_id')->nullable()->constrained('subscription_renewal_policies')->nullOnDelete();
            $table->string('status')->default('NEW');
            $table->string('renewal_stage')->default('NOT_DUE');
            $table->string('current_subscription_status')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->integer('days_until_expiry')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->foreignId('billing_invoice_id')->nullable()->constrained('saas_billing_invoices')->nullOnDelete();
            $table->foreignId('billing_account_id')->nullable()->constrained('saas_billing_accounts')->nullOnDelete();
            $table->string('last_payment_evidence_status')->nullable();
            $table->string('priority')->default('NORMAL'); // LOW | NORMAL | HIGH | URGENT
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qualified_for_manual_renewal_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('renewal_stage');
            $table->index('priority');
            $table->index('tenant_id');
            $table->index('tenant_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_candidates');
    }
};
