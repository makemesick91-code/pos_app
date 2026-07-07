<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20 — SaaS package catalog. An internal/admin-only commercial package
 * definition: package code, target segment, feature boundaries, device/store/user
 * limits, onboarding level, support level, status, pricing metadata and evidence
 * reference.
 *
 * Pricing here is GOVERNANCE METADATA ONLY — it creates no real billing, opens no
 * public signup, and does not bypass the existing SubscriptionPlan /
 * TenantSubscription / RegisteredDevice runtime enforcement from Sprint 10. No
 * secrets or private customer data are stored on the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_package_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('package_code')->unique();
            $table->string('name');
            $table->string('target_segment'); // WARUNG | TOKO_KECIL | KEDAI | LAUNDRY | RETAIL | APOTEK_LIGHT | GENERAL_UMKM
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | ACTIVE | RETIRED | BLOCKED
            $table->unsignedInteger('monthly_price')->nullable();
            $table->string('currency', 8)->default('IDR');
            $table->unsignedInteger('device_limit')->nullable();
            $table->unsignedInteger('store_limit')->nullable();
            $table->unsignedInteger('user_limit')->nullable();
            $table->string('onboarding_level')->default('SELF_GUIDED'); // SELF_GUIDED | ASSISTED | MANAGED
            $table->string('support_level')->default('BASIC'); // BASIC | STANDARD | PRIORITY
            $table->json('feature_flags')->nullable();
            $table->json('included_modules')->nullable();
            $table->json('excluded_modules')->nullable();
            $table->text('commercial_notes')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('target_segment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_package_catalogs');
    }
};
