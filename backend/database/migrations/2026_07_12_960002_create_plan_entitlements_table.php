<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — per-plan feature entitlement flags. One row per (plan,
 * entitlement_key); enabled decides whether the plan grants the feature. Synced
 * from config/tenant_plan.php by TenantPlanRegistrar. No secrets are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_plan_id')->constrained('tenant_plans')->cascadeOnDelete();
            $table->string('entitlement_key');
            $table->boolean('enabled')->default(false);
            $table->string('limit_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_plan_id', 'entitlement_key'], 'plan_entitlements_plan_key_unique');
            $table->index('entitlement_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entitlements');
    }
};
