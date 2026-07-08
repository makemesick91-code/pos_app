<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — per-tenant feature entitlement overrides. A limited, platform-admin
 * only, audit-logged mechanism to enable/disable a single feature for a tenant on
 * top of its plan (TPE-R006, TPE-R007). An override NEVER grants access to a
 * suspended/cancelled/archived tenant — tenant lifecycle enforcement always runs
 * first (TPE-R004/R005). Reason is mandatory and sanitized; no secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entitlement_key');
            $table->boolean('enabled');
            $table->string('status')->default('active'); // active | revoked
            $table->string('reason', 1000);
            $table->string('reason_category')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'entitlement_key']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entitlement_overrides');
    }
};
