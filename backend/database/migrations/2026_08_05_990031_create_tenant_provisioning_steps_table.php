<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 33 — per-step provisioning trace. Each provisioning step (resolve_plan,
 * create_tenant, activate_trial, provision_first_branch, provision_owner_admin,
 * provision_first_cashier, prepare_device_register, seed_default_data,
 * prepare_invoice, prepare_payment_intent, finalize) is recorded here.
 *
 * The step row IS the provisioning audit record (ONB-R006/R023): it stores the
 * subject type/id it created, a redacted metadata blob and a redacted failure
 * reason — never a password, token, signature or PII. `idempotency_key` is
 * unique per run+step so a retry resumes without duplicating work (ONB-R021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_provisioning_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_provisioning_run_id')->constrained('tenant_provisioning_runs')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('step_key');
            $table->string('status')->default('pending');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->string('reason_code')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_provisioning_run_id', 'step_key']);
            $table->index('status');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_provisioning_steps');
    }
};
