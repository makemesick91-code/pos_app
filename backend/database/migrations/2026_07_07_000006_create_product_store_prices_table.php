<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store-specific product price overrides — tenant-owned and store-scoped.
 * Exactly one active override per (tenant_id, store_id, product_id).
 * The override, when active, produces the effective_selling_price surfaced to
 * Android via the product sync endpoint. See foundation sections 9, 11, 14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_store_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->unique(['tenant_id', 'store_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_store_prices');
    }
};
