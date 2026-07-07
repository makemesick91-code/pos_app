<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 21 — public website risk register row. Open CRITICAL/HIGH without a
 * valid accepted risk forces NO-GO; open MEDIUM forces WATCH. Accepted risk for
 * CRITICAL/HIGH/MEDIUM requires an approver, a reason, and an expiry. No secrets
 * or private customer data are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_website_risks', function (Blueprint $table) {
            $table->id();
            $table->string('risk_reference')->unique();
            $table->string('area'); // CONTENT_CLAIM | PRICING_ALIGNMENT | PRIVACY | LEGAL_TERMS | LEAD_CAPTURE | SEO | PERFORMANCE | ACCESSIBILITY | SECURITY | OPERATIONS | OTHER
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
        Schema::dropIfExists('public_website_risks');
    }
};
