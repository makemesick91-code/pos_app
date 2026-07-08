<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 27 — Report Export Metering & Usage Event Ledger Governance Foundation.
 *
 * The tenant_usage_events table is the append-only, server-side source of truth
 * for tenant usage events (UEL-R001, UEL-R002). Monthly usage meters
 * (reports.exports.monthly) are derived from it by counting events in a stable
 * server-side period key (UEL-R005, UEL-R006). Recording is idempotent through a
 * per-tenant unique idempotency_key so a retried request never double counts
 * (UEL-R004). Metadata is redacted before persistence and never holds secrets,
 * tokens, credentials, or raw PII (UEL-R003). No secrets, no real customer data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_key');            // e.g. report.exported
            $table->string('event_category');       // e.g. report_export
            $table->string('meter_key')->nullable(); // e.g. reports.exports.monthly
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('occurred_at');        // server-side event time
            $table->string('period_key');            // stable monthly key e.g. 2026-07
            $table->string('idempotency_key');       // dedupe key (header or fingerprint)
            $table->string('source')->default('api'); // api | web | system
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('request_fingerprint')->nullable();
            $table->json('metadata')->nullable();    // redacted only
            $table->timestamps();

            $table->index(['tenant_id', 'meter_key', 'period_key']);
            $table->index(['tenant_id', 'event_key', 'occurred_at']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_events');
    }
};
