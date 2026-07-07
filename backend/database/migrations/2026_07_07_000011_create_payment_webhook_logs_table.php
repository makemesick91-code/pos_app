<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 — QRIS payment gateway webhook audit log.
 *
 * Every inbound webhook is persisted here BEFORE any payment mutation, so an
 * invalid-signature or unknown-reference callback is still recorded (but never
 * allowed to update a payment). tenant_id/store_id/payment_id are nullable
 * because they are only known once the payload is resolved to a local payment.
 * The raw payload is stored verbatim for reconciliation and dispute handling.
 * See foundation sections 13 and 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('event_type')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('payload');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 20)->default('received');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('provider_reference');
            $table->index('payment_id');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'store_id']);
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
