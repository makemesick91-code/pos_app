<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 21 — an interest-only public lead submission. A lead NEVER creates a
 * tenant, user, subscription, or device and NEVER triggers real email/WhatsApp.
 * Consent is required (consent_accepted_at). Secret-looking input is sanitized in
 * the service. Follow-up is a manual, human process.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_interest_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('lead_reference')->unique();
            $table->string('status')->default('NEW'); // NEW | REVIEWED | CONTACTED | QUALIFIED | DISQUALIFIED | ARCHIVED | SPAM
            $table->string('business_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('business_type')->nullable();
            $table->unsignedInteger('estimated_store_count')->nullable();
            $table->unsignedInteger('estimated_device_count')->nullable();
            $table->string('interest_package_code')->nullable();
            $table->text('message')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_interest_submissions');
    }
};
