<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 31 — tenant billing payment intents (provider-neutral settlement).
 *
 * An intent represents a request to pay a Sprint 30 tenant billing invoice
 * through a provider/channel (QRIS/mock_qris). It is idempotent per invoice +
 * provider + channel via a deterministic `idempotency_key` (PGW-R003) and can
 * never be created for an already-paid invoice (PGW-R004). `amount` mirrors the
 * invoice outstanding amount; partial/overpayment are refused by default
 * (PGW-R005/R006). `provider_reference` is unique so one provider payment settles
 * once (PGW-R012). `metadata` is redacted — never secrets/PII (PGW-R011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('tenant_billing_invoices')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('channel', 40)->default('mock_qris');
            $table->string('period_key', 7)->nullable(); // YYYY-MM, copied from the invoice for reporting
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('IDR');
            $table->string('status')->default('pending'); // pending|requires_action|paid|expired|failed|cancelled
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('platform_admin');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference'], 'tbpi_provider_reference_unique');
            $table->index(['invoice_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['provider', 'channel', 'status']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_payment_intents');
    }
};
