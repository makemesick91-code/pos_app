<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant subscriptions (Sprint 10). A tenant-owned link to a subscription plan
 * with a backend-computed lifecycle status (TRIAL/ACTIVE/GRACE/EXPIRED/
 * CANCELLED/SUSPENDED). The status a tenant is *allowed* to act on is always
 * resolved by SubscriptionStatusService from the date columns — never trusted
 * from the client. Exactly one current subscription per tenant is enforced by
 * the service layer. See foundation sections 8, 9, 16 and Sprint 10 evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
            $table->string('status', 20)->default('TRIAL');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('ends_at');
            $table->index('trial_ends_at');
            $table->index('grace_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
