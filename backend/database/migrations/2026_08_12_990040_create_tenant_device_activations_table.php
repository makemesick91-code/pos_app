<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 34 — governed Android device/register activation.
 *
 * Tracks a device activation lifecycle (pending → activated → revoked/expired/
 * failed). The activation token is stored ONLY as a sha256 hash; the raw token is
 * never persisted (ADR-R003). `device_fingerprint_hash` is a non-reversible hash
 * of the device fingerprint. `device_label`, `failure_reason` and `metadata_json`
 * are redacted/safe — they never store passwords, tokens, signatures or PII.
 *
 * An activation links to the Sprint 10 `registered_devices` row it authorises and
 * (optionally) to the Sprint 33 `tenant_provisioning_runs` row that prepared it.
 * See docs/architecture/sprint-34-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_device_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // "branch"/"register" map to a Store in this codebase (no Branch model).
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->unsignedBigInteger('register_id')->nullable();
            $table->foreignId('device_id')->nullable()->constrained('registered_devices')->nullOnDelete();
            $table->foreignId('provisioning_run_id')->nullable()->constrained('tenant_provisioning_runs')->nullOnDelete();
            $table->string('activation_status')->default('pending');
            // Never store a raw token — sha256 hash only (nullable so a consumed
            // activation may keep it for idempotency lookups).
            $table->string('activation_token_hash')->nullable();
            $table->string('device_fingerprint_hash')->nullable();
            $table->string('device_label')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            // One prepared token per tenant; a re-prepared token replaces the row.
            $table->unique(['tenant_id', 'activation_token_hash'], 'device_activations_tenant_token_unique');
            $table->index(['tenant_id', 'device_fingerprint_hash', 'activation_status'], 'device_activations_fingerprint_idx');
            $table->index(['tenant_id', 'register_id', 'activation_status'], 'device_activations_register_idx');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_device_activations');
    }
};
