<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 25 — the tenant lifecycle event trail.
 *
 * Every governance transition (manual_suspend, manual_lift, lifecycle_transition)
 * is appended here with the previous/new lifecycle status, a sanitized reason,
 * an optional reason category, the acting admin, and redacted metadata. This is
 * the audit trail for tenant lifecycle enforcement; it never stores secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('action'); // manual_suspend | manual_lift | lifecycle_transition
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->string('reason', 1000)->nullable();
            $table->string('reason_category')->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manual_suspension_id')->nullable()->constrained('tenant_manual_suspensions')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('action');
            $table->index('new_status');
            $table->index(['tenant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_lifecycle_events');
    }
};
