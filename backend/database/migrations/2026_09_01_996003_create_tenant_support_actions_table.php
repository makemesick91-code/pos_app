<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 35 — the app-specific support action ledger (SUP-R006/R024/R026).
 *
 * Every support mutation and every high-risk read context (read-only context
 * start/end, device revoke/reactivate, incident create/update, note add,
 * diagnostic export, blocked/sync-failure review) is recorded here AND, for
 * mutations, in the shared `admin_audit_logs` via AdminAuditLogger. Denied
 * support actions are recorded too. `metadata_json` is redacted; `reason_code`
 * is a safe enumerable code (SUP-R005/R025). See docs/architecture/sprint-35-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_support_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_key');
            $table->string('action_type')->default('other');
            $table->string('status')->default('completed');
            $table->string('reason_code')->nullable();
            $table->string('related_subject_type')->nullable();
            $table->unsignedBigInteger('related_subject_id')->nullable();
            $table->foreignId('support_session_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'action_key'], 'support_actions_tenant_key_idx');
            $table->index('actor_user_id', 'support_actions_actor_idx');
            $table->index(['action_type', 'status'], 'support_actions_type_status_idx');
            $table->index('created_at', 'support_actions_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_support_actions');
    }
};
