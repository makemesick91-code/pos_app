<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing collection risk register row. Open CRITICAL/HIGH
 * without a valid accepted risk forces NO-GO; open MEDIUM forces WATCH. Accepted
 * risk requires approver, reason and expiry; an expired accepted risk re-blocks.
 * No secrets or private customer data stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_collection_risks', function (Blueprint $table) {
            $table->id();
            $table->string('risk_reference')->unique();
            $table->foreignId('billing_account_id')->nullable()->constrained('saas_billing_accounts')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('saas_billing_invoices')->nullOnDelete();
            $table->string('area'); // PAYMENT_DELAY | DISPUTE | INVOICE_ACCURACY | COLLECTION_SLA | PACKAGE_ALIGNMENT | SUBSCRIPTION_STATUS | LEGAL_PRIVACY | ACCOUNTING_EXPORT | MANUAL_EVIDENCE_QUALITY | OTHER
            $table->string('severity'); // CRITICAL | HIGH | MEDIUM | LOW | INFO
            $table->string('status')->default('OPEN'); // OPEN | MITIGATED | ACCEPTED_RISK | CLOSED
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mitigation')->nullable();
            $table->timestamp('accepted_risk_at')->nullable();
            $table->foreignId('accepted_risk_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('accepted_risk_reason')->nullable();
            $table->timestamp('accepted_risk_expires_at')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('severity');
            $table->index('status');
            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_collection_risks');
    }
};
