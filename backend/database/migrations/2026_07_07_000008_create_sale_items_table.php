<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sale line items — tenant/store-owned snapshots of what was sold. product_name
 * and unit_price are snapshotted at checkout time from the backend-resolved
 * product/price so later catalog edits never rewrite historical transactions.
 * subtotal is always computed server-side. See foundation sections 9, 11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name');
            $table->string('product_sku', 64)->nullable();
            $table->string('product_barcode', 64)->nullable();
            $table->string('unit', 32)->default('pcs');
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
