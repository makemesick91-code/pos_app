<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — tenant plan catalogue (server-side source of truth, TPE-R001).
 *
 * Plans are the persisted definition of what a tenant may do; they are synced
 * from config/tenant_plan.php by TenantPlanRegistrar and are never created from
 * client input. Metadata is controlled/redacted and never stores secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status')->default('active'); // active | inactive
            $table->string('billing_interval')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plans');
    }
};
