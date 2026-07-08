<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 32 — append-only-ish audit trail of runtime entitlement decisions.
 *
 * Only denied / degraded / read_only / bypassed decisions (and never routine
 * allowed reads) are persisted (ENT-R018). metadata_json is already redacted by
 * EntitlementRedactor before it reaches here, so this table can never leak
 * secrets or PII. There is no tenant-facing route that writes this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_entitlement_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('entitlement_key')->nullable();
            $table->string('resource_type')->nullable();
            $table->string('action')->nullable();
            // allowed | denied | degraded | read_only | bypassed
            $table->string('decision');
            $table->string('reason_code');
            $table->string('plan_code')->nullable();
            $table->integer('current_usage')->nullable();
            $table->integer('limit_value')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('subscription_state')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'entitlement_key'], 'ted_tenant_entitlement_idx');
            $table->index(['decision', 'reason_code'], 'ted_decision_reason_idx');
            $table->index('created_at', 'ted_created_at_idx');
            $table->index('actor_user_id', 'ted_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entitlement_decisions');
    }
};
