<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 25 — manual tenant suspension records (governance source of truth).
 *
 * A row with status = ACTIVE means a platform admin has manually suspended the
 * tenant; the tenant lifecycle guard blocks operational access while it is
 * active. Lifting sets status = LIFTED and stamps lifted_at/lifted_by. Manual
 * suspension has precedence over subscription renewal/dunning automation, which
 * never writes this table. Reason is mandatory and sanitized; no secrets,
 * tokens, or payment credentials are ever stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_manual_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status')->default('ACTIVE'); // ACTIVE | LIFTED
            $table->string('reason', 1000);
            $table->string('reason_category')->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('lifted_at')->nullable();
            $table->string('lift_reason', 1000)->nullable();
            $table->foreignId('suspended_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lifted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_manual_suspensions');
    }
};
