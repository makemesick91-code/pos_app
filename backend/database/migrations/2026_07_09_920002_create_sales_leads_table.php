<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 22 — a sales lead. May be imported from a Sprint 21 lead interest
 * submission (lead_interest_submission_id) or created manually. A sales lead is
 * intake/pipeline data only: it NEVER creates a tenant, user, subscription, or
 * device and NEVER triggers real billing/CRM/messaging. ready_for_onboarding_at
 * means a manual onboarding review is due — not automatic provisioning. No
 * secrets or payment credentials are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_reference')->unique();
            $table->foreignId('lead_interest_submission_id')->nullable()->constrained('lead_interest_submissions')->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained('sales_pipeline_stages')->nullOnDelete();
            $table->string('status')->default('NEW'); // NEW | IN_REVIEW | CONTACTED | QUALIFIED | DEMO_SCHEDULED | PROPOSAL_SENT | NEGOTIATION | WON_READY_FOR_ONBOARDING | LOST | ARCHIVED | SPAM
            $table->string('source')->default('manual');
            $table->string('business_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('business_type')->nullable();
            $table->integer('estimated_store_count')->nullable();
            $table->integer('estimated_device_count')->nullable();
            $table->string('interest_package_code')->nullable();
            $table->integer('qualification_score')->nullable();
            $table->string('priority')->default('NORMAL'); // LOW | NORMAL | HIGH | URGENT
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamp('ready_for_onboarding_at')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source');
            $table->index('priority');
            $table->index('pipeline_stage_id');
            $table->index('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_leads');
    }
};
