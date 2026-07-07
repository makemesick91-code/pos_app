<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily closing snapshot (Sprint 9). A tenant/store-owned, per-business-date
 * record of the day's authoritative totals. Every total is computed by the
 * backend report services at close time — client-provided totals are never
 * trusted. The composite unique index guarantees exactly one closing per
 * (tenant_id, store_id, business_date); a duplicate close request replays the
 * existing row rather than creating a second one. Reopen workflows are out of
 * scope for Sprint 9. See foundation sections 9, 11, 16 and Sprint 9 evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->date('business_date');
            $table->foreignId('closed_by')->constrained('users');
            $table->timestamp('closed_at');
            $table->string('status', 20)->default('CLOSED');
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('cancelled_sales_count')->default(0);
            $table->decimal('cash_total', 14, 2)->default(0);
            $table->decimal('qris_total', 14, 2)->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('change_total', 14, 2)->default(0);
            $table->decimal('inventory_sale_out_qty', 14, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'store_id', 'business_date'], 'daily_closings_unique');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'business_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
