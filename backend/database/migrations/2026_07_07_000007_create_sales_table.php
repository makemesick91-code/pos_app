<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sales header — the tenant/store-owned record of a completed (or cancelled)
 * transaction. Totals are always recalculated server-side; the invoice number
 * is backend-generated and unique per (tenant_id, store_id). Sprint 4 supports
 * online CASH checkout only. See foundation sections 9, 11, 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('invoice_number', 64);
            $table->timestamp('sale_date')->useCurrent();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('change_total', 14, 2)->default(0);
            $table->string('payment_status', 20)->default('UNPAID');
            $table->string('sync_status', 20)->default('SYNCED');
            $table->string('source', 20)->default('ANDROID_ONLINE');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['tenant_id', 'store_id', 'invoice_number']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'sale_date']);
            $table->index(['tenant_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
