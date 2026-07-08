<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 34 — Android sync batch idempotency ledger.
 *
 * One row per sync batch submitted by an activated device. `idempotency_key` and
 * (tenant_id, client_batch_id) are unique so a replayed batch resumes the existing
 * row and never re-mutates (ADR-R014). Counts summarise the per-item outcome.
 * `failure_reason` and `metadata_json` are redacted/safe (ADR-R022). See
 * docs/architecture/sprint-34-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_android_sync_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->unsignedBigInteger('register_id')->nullable();
            $table->foreignId('device_activation_id')->nullable()->constrained('tenant_device_activations')->nullOnDelete();
            $table->foreignId('cashier_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_batch_id');
            $table->string('idempotency_key');
            $table->string('status')->default('received');
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'client_batch_id'], 'sync_batches_tenant_client_unique');
            $table->unique('idempotency_key', 'sync_batches_idempotency_unique');
            $table->index(['tenant_id', 'status'], 'sync_batches_tenant_status_idx');
            $table->index(['tenant_id', 'register_id'], 'sync_batches_tenant_register_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_android_sync_batches');
    }
};
