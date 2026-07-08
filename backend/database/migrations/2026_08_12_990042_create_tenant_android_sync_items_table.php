<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 34 — per-item result trail for an Android sync batch.
 *
 * One row per client item. (sync_batch_id, client_item_id) is unique so the same
 * client item can never be double-recorded within a batch (ADR-R013). `payload_hash`
 * is a non-reversible hash of the item payload (never the raw payload). `status`
 * records accepted/rejected/duplicate/conflict/failed/skipped and `conflict_code`
 * / `failure_reason` are deterministic + redacted (ADR-R016/R022). See
 * docs/architecture/sprint-34-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_android_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_batch_id')->constrained('tenant_android_sync_batches')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('client_item_id');
            $table->string('item_type')->default('sale');
            $table->string('action')->default('create');
            $table->string('status')->default('skipped');
            $table->string('server_subject_type')->nullable();
            $table->unsignedBigInteger('server_subject_id')->nullable();
            $table->string('conflict_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('payload_hash')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['sync_batch_id', 'client_item_id'], 'sync_items_batch_client_unique');
            $table->index(['tenant_id', 'item_type', 'status'], 'sync_items_tenant_type_status_idx');
            $table->index('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_android_sync_items');
    }
};
