<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — per-plan usage limits. One row per (plan, limit_key). A null
 * limit_value with unlimited=false means the limit is not configured for the
 * plan; unlimited=true means no numeric cap. Period is lifetime/monthly/daily/
 * current. Synced from config/tenant_plan.php by TenantPlanRegistrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_usage_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_plan_id')->constrained('tenant_plans')->cascadeOnDelete();
            $table->string('limit_key');
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->boolean('unlimited')->default(false);
            $table->string('period')->default('lifetime');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_plan_id', 'limit_key'], 'plan_usage_limits_plan_key_unique');
            $table->index('limit_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usage_limits');
    }
};
