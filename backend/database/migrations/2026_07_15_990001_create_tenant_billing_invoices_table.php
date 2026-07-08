<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 30 — tenant billing invoices (server-side, plan-priced, idempotent).
 *
 * An invoice is generated from the tenant's ACTIVE plan pricing for a canonical
 * billing period (BIL-R002/R003). Idempotency is enforced two ways: a unique
 * (tenant_id, period_key, source) pair (BIL-R005) and a unique idempotency_key.
 * `status` is the document lifecycle (draft|issued|void|cancelled); the separate
 * `collection_state` axis tracks payment. Amounts are whole-rupiah integers,
 * matching subscription_plans.price_monthly. metadata is redacted — never secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_plan_id')->nullable()->constrained('tenant_plans')->nullOnDelete();
            $table->string('plan_key', 40);
            $table->string('invoice_number')->unique();
            $table->string('period_key', 7); // YYYY-MM
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at');
            $table->string('currency', 8)->default('IDR');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('status')->default('draft'); // draft | issued | void | cancelled
            $table->string('collection_state')->default('not_due'); // not_due|pending|paid|failed|overdue|written_off|cancelled
            $table->string('source')->default('platform_admin');
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period_key', 'source'], 'tbi_tenant_period_source_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'collection_state']);
            $table->index('due_at');
            $table->index('period_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_invoices');
    }
};
