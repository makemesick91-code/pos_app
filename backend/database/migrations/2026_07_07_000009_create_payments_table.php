<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment records — tenant/store-owned, linked to a sale. Sprint 4 runtime uses
 * CASH only (provider MANUAL, status PAID); the QRIS/gateway columns exist for
 * later sprints but are never populated by gateway logic here. Credentials and
 * gateway responses (raw_response) are never surfaced to Android.
 * See foundation sections 9, 13, 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('method', 20);
            $table->decimal('amount', 14, 2);
            $table->string('status', 20);
            $table->string('provider', 20)->default('MANUAL');
            $table->string('provider_reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'method']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
