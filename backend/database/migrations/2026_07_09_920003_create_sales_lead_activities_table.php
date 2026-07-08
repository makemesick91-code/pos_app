<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 22 — a sales lead activity (note/call/manual WhatsApp/manual email/demo/
 * proposal/follow-up/status change/assignment/qualification/risk review/handover
 * review). WHATSAPP_MANUAL and EMAIL_MANUAL are MANUAL NOTES only — no real
 * message is ever sent, no external CRM/webhook is ever called. No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity_reference')->unique();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type'); // NOTE | CALL | WHATSAPP_MANUAL | EMAIL_MANUAL | DEMO | PROPOSAL | FOLLOW_UP | STATUS_CHANGE | ASSIGNMENT | QUALIFICATION | RISK_REVIEW | ONBOARDING_HANDOVER_REVIEW
            $table->string('status')->default('PLANNED'); // PLANNED | DONE | CANCELLED | SKIPPED
            $table->string('summary');
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('activity_type');
            $table->index('status');
            $table->index('sales_lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_activities');
    }
};
