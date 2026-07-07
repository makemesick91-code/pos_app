<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans (Sprint 10). Backend-owned catalogue of SaaS plans that
 * cap a tenant's stores/devices/products. Plans are never created or mutated by
 * client input — they are seeded/managed by the platform. Each plan carries a
 * unique code (lite/starter/pro) and hard limits enforced by the backend when a
 * tenant registers a device or opens a store. See foundation sections 8, 9, 16,
 * 21 and Sprint 10 evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price_monthly', 14, 2)->default(0);
            $table->unsignedInteger('max_stores')->default(1);
            $table->unsignedInteger('max_devices')->default(1);
            $table->unsignedInteger('max_products')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
