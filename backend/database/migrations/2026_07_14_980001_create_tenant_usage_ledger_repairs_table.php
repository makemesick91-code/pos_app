<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 28 — Usage Ledger Anomaly Detection & Governed Repair Foundation.
 *
 * The tenant_usage_events ledger (Sprint 27) is append-only and MUST NOT be
 * updated or deleted by normal runtime or by repair (ULR-R009, ULR-R010). When a
 * governed repair needs to correct effective usage (e.g. remove a duplicate
 * double-count), it appends a governed repair record here instead of mutating the
 * original event. Effective monthly usage is then derived as the ledger count
 * PLUS the signed quantity_delta of the applicable repair records, clamped so it
 * can never go negative (ULR-R010, ULR-R013).
 *
 * Repair records are only ever written by the governed `usage-ledger:repair-apply`
 * CLI command — there is deliberately no runtime API route that creates, updates,
 * or deletes them (ULR-R009). Each record carries reason + applied_by + applied_at
 * and redacted metadata so it is itself an auditable governed artifact (ULR-R008).
 * repair_key is unique per tenant so re-applying the same repair is idempotent and
 * never creates correction drift (ULR-R011). Contains no secrets, no raw PII.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_ledger_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('meter_key');
            $table->string('period_key');
            $table->string('repair_key');            // deterministic idempotency key
            $table->string('repair_type');           // e.g. duplicate_usage_correction
            $table->integer('quantity_delta');       // signed correction to usage count
            $table->string('reason');                // governed apply reason
            $table->string('applied_by');            // actor label (platform-admin/system)
            $table->timestamp('applied_at');
            $table->json('dry_run_payload')->nullable(); // redacted plan snapshot
            $table->json('metadata')->nullable();       // redacted only
            $table->timestamps();

            $table->unique(['tenant_id', 'repair_key']);
            $table->index(['tenant_id', 'meter_key', 'period_key']);
            $table->index('repair_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_ledger_repairs');
    }
};
