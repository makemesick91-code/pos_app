<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger-based inventory foundation (Sprint 8). Stock is NEVER stored as a
 * mutable product column — it is always derived from the signed sum of the
 * rows in this table (see StockCalculator).
 *
 * Every movement is tenant-owned, store-owned, and product-owned. `qty` is
 * always positive; `signed_qty` carries the stock impact (+ for OPENING /
 * ADJUSTMENT_IN / RETURN_IN, - for ADJUSTMENT_OUT / SALE_OUT) and is only ever
 * written by the backend, never by a client.
 *
 * Idempotency: a SALE_OUT row references the originating sale item
 * (reference_type = "sale_item", reference_id = sale_items.id). The composite
 * unique index guarantees a retried/replayed offline sale can never create a
 * second SALE_OUT for the same line. Rows with NULL reference (opening,
 * manual adjustments) are treated as distinct by SQLite/MySQL/PostgreSQL, so
 * the guard only constrains referenced movements. See foundation sections
 * 11, 14 and Sprint 8 evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('qty', 14, 2);
            $table->decimal('signed_qty', 14, 2);
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('source', 32)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'store_id', 'product_id']);
            $table->index('movement_type');
            $table->index(['reference_type', 'reference_id']);

            // Idempotency guard for referenced movements (e.g. SALE_OUT per
            // sale item). NULL references remain non-unique across DB engines.
            $table->unique(
                ['tenant_id', 'store_id', 'product_id', 'movement_type', 'reference_type', 'reference_id'],
                'inventory_movements_reference_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
