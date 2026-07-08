<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription renewal activity. Manual communication/review notes
 * only. A WHATSAPP_MANUAL / EMAIL_MANUAL activity is an internal record of an
 * external manual action — NO real message is ever sent. No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity_reference')->unique();
            $table->foreignId('candidate_id')->nullable()->constrained('subscription_renewal_candidates')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('activity_type');
            $table->string('status')->default('PLANNED'); // PLANNED | DONE | CANCELLED | SKIPPED
            $table->string('summary');
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('activity_type');
            $table->index('status');
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_activities');
    }
};
