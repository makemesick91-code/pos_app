<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Products — tenant-owned. A null store_id means the product is global for the
 * tenant; a set store_id scopes it to one store/branch. category_id is
 * optional but, when present, must reference a category of the same tenant
 * (enforced in request validation).
 *
 * sku is unique per tenant (DB constraint). barcode uniqueness per tenant is
 * enforced at the application layer to stay portable across SQLite/PostgreSQL
 * when barcode is null. See foundation sections 9, 11 and Sprint 2 evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('unit')->default('pcs');
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->boolean('is_stock_tracked')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'barcode']);
            $table->unique(['tenant_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
