<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — tenant → plan assignment (server-side source of truth for which
 * plan a tenant is on, TPE-R001). An ACTIVE row within its effective window is
 * the authoritative plan. Assignment is platform-admin only and audit-logged
 * (TPE-R006, TPE-R007). It NEVER bypasses tenant lifecycle enforcement
 * (TPE-R004/R005). Metadata is redacted; no secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_plan_id')->constrained('tenant_plans')->cascadeOnDelete();
            $table->string('status')->default('active'); // active | scheduled | expired | cancelled
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->string('source')->default('platform_admin');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plan_assignments');
    }
};
