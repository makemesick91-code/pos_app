<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription dunning notice. This is a MANUAL reminder queue
 * record only. MARKED_SENT_MANUALLY means an admin recorded an external manual
 * action (WhatsApp/email/call). No real email/WhatsApp/SMS is ever sent and no
 * secrets are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_dunning_notices', function (Blueprint $table) {
            $table->id();
            $table->string('notice_reference')->unique();
            $table->foreignId('candidate_id')->constrained('subscription_renewal_candidates')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->foreignId('billing_invoice_id')->nullable()->constrained('saas_billing_invoices')->nullOnDelete();
            $table->string('notice_type'); // RENEWAL_REMINDER | PAYMENT_REMINDER | GRACE_NOTICE | OVERDUE_NOTICE | FINAL_MANUAL_REVIEW_NOTICE
            $table->string('status')->default('PLANNED'); // PLANNED | PREPARED | MARKED_SENT_MANUALLY | COMPLETED | CANCELLED | SKIPPED
            $table->string('channel')->default('IN_APP_ADMIN_NOTE'); // WHATSAPP_MANUAL | EMAIL_MANUAL | CALL_MANUAL | IN_APP_ADMIN_NOTE | OTHER_MANUAL
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('marked_sent_manually_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary');
            $table->string('message_template_key')->nullable();
            $table->text('manual_message_preview')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('notice_type');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_dunning_notices');
    }
};
