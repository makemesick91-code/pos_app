<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 35 — support-safe read-only context (and, if ever governed-enabled, an
 * impersonation) session (SUP-R017/R018/R019).
 *
 * A session is tenant-scoped and time-bound (`expires_at`). `scope_json` and
 * `metadata_json` are redacted/safe and NEVER hold a raw credential/token — the
 * session only records that a platform admin opened a read-only context, not any
 * borrowed secret. Impersonation sessions are disabled by default and, when
 * created at all, are always read-only-safe. See docs/architecture/sprint-35-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('session_type')->default('read_only_context');
            $table->string('status')->default('active');
            $table->string('reason_code');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->json('scope_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'support_sessions_tenant_idx');
            $table->index('actor_user_id', 'support_sessions_actor_idx');
            $table->index('status', 'support_sessions_status_idx');
            $table->index('expires_at', 'support_sessions_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_support_sessions');
    }
};
