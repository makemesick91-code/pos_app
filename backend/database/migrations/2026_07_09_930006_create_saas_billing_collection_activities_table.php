<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing collection activity. Manual collection governance
 * only: WHATSAPP_MANUAL / EMAIL_MANUAL are notes, never real sending. No secrets
 * stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_collection_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity_reference')->unique();
            $table->foreignId('billing_account_id')->nullable()->constrained('saas_billing_accounts')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('saas_billing_invoices')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type'); // NOTE | CALL | WHATSAPP_MANUAL | EMAIL_MANUAL | INVOICE_ISSUED | PAYMENT_FOLLOW_UP | PAYMENT_REVIEW | DISPUTE_REVIEW | OVERDUE_REVIEW | COLLECTION_ESCALATION
            $table->string('status')->default('PLANNED'); // PLANNED | DONE | CANCELLED | SKIPPED
            $table->string('summary');
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('activity_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_collection_activities');
    }
};
