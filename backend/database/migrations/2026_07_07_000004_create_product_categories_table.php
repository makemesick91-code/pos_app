<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product categories — tenant-owned. A null store_id means the category is
 * global for the tenant; a set store_id scopes it to one store/branch.
 * See foundation sections 9, 11 and Sprint 2 evidence.
 *
 * Uniqueness of (tenant_id, store_id, name) is enforced at the application
 * layer (validation) because SQLite and PostgreSQL treat NULLs in composite
 * unique indexes differently; a plain composite index keeps lookups fast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
