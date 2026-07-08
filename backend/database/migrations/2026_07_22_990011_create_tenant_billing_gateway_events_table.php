<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 31 — verified gateway/webhook event ingestion records (idempotent).
 *
 * Every provider webhook is stored here exactly once. `provider_event_id` (when
 * present) is unique per provider, and `payload_hash` is unique per provider so a
 * replay is detected and never reprocessed (PGW-R008). `signature_verified`
 * records the verification outcome — the RAW signature/secret is NEVER stored,
 * only a truncated `signature_hash` fingerprint (PGW-R011). `normalized_status`
 * drives settlement; a failed/cancelled/expired event updates state but never
 * marks an invoice paid (PGW-R009). `metadata`/`failure_reason` are redacted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_type')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->foreignId('payment_intent_id')->nullable()->constrained('tenant_billing_payment_intents')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('tenant_billing_invoices')->nullOnDelete();
            $table->string('payload_hash', 64);
            $table->string('signature_hash', 64)->nullable(); // truncated fingerprint, never the raw signature
            $table->boolean('signature_verified')->default(false);
            $table->string('status')->default('received'); // received|verified|rejected|processed|ignored|replayed
            $table->string('normalized_status')->nullable(); // paid|failed|expired|cancelled|pending|requires_action
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable(); // redacted machine reason, never a raw payload/secret
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'tbge_provider_event_unique');
            $table->unique(['provider', 'payload_hash'], 'tbge_provider_payload_unique');
            $table->index(['provider', 'status']);
            $table->index('provider_reference');
            $table->index('payment_intent_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_gateway_events');
    }
};
