<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 12 — Tenant Onboarding & Demo Data Foundation.
 *
 * Records each platform-admin-driven tenant onboarding run: which tenant/store/
 * owner/subscription it created, whether demo data was seeded, and a
 * backend-generated checklist. `onboarding_reference` is unique so a duplicate
 * request replays the existing run instead of creating a second tenant.
 *
 * `metadata` holds a backend-owned demo manifest (the ids seeded for this run)
 * used by the guarded demo-data reset — it must never store passwords or
 * plain secrets. See docs/sprints/sprint-12-tenant-onboarding-demo-data-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_onboarding_runs', function (Blueprint $table) {
            $table->id();
            $table->string('onboarding_reference')->unique();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('default_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('status')->default('PENDING'); // PENDING | RUNNING | COMPLETED | FAILED
            $table->string('tenant_name');
            $table->string('store_name')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->boolean('demo_data_enabled')->default(false);
            $table->timestamp('demo_data_seeded_at')->nullable();
            $table->timestamp('demo_data_reset_at')->nullable();
            $table->json('checklist')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tenant_id');
            $table->index('requested_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onboarding_runs');
    }
};
