<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 30 — tenant billing payments (payment collection state foundation).
 *
 * A payment is a recorded fact against an invoice — this is NOT a payment gateway
 * integration (BIL foundation). Mutations are platform-admin only and audit-logged
 * (BIL-R007/R008). `amount` is whole-rupiah; it may never be negative and (unless
 * overpayment is explicitly allowed) never exceeds the invoice outstanding amount
 * (BIL-R009). Idempotency_key prevents duplicate records. A failed/cancelled
 * payment never marks the invoice paid (BIL-R010). metadata is redacted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('tenant_billing_invoices')->cascadeOnDelete();
            $table->string('payment_reference')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('IDR');
            $table->string('method')->default('manual');
            $table->string('status')->default('recorded'); // pending|recorded|confirmed|failed|cancelled|refunded
            $table->string('collection_state')->default('pending'); // mirror of invoice collection axis at record time
            $table->timestamp('received_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('platform_admin');
            $table->string('idempotency_key')->unique();
            $table->string('reason', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_payments');
    }
};
