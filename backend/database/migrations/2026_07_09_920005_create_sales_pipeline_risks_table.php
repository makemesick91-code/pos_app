<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 22 — a sales pipeline risk register row. A tracked pipeline risk: area,
 * severity, status, owner, mitigation and accepted-risk governance (approver,
 * reason, expiry). Open CRITICAL/HIGH without a valid accepted risk forces NO-GO;
 * open MEDIUM forces WATCH. No secrets or private customer data are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_pipeline_risks', function (Blueprint $table) {
            $table->id();
            $table->string('risk_reference')->unique();
            $table->foreignId('sales_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $table->string('area'); // LEAD_QUALITY | PRICING_EXPECTATION | PACKAGE_MISALIGNMENT | ONBOARDING_CAPACITY | LEGAL_PRIVACY | PAYMENT_BILLING_EXPECTATION | DATA_QUALITY | FOLLOW_UP_SLA | OPERATIONS | OTHER
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
        Schema::dropIfExists('sales_pipeline_risks');
    }
};
