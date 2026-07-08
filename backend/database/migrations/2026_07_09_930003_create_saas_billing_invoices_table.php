<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing invoice. A platform-to-tenant billing invoice, NOT a
 * POS cashier receipt. Issuing it NEVER triggers a QRIS/payment gateway and NEVER
 * auto-suspends a tenant. Totals are server-calculated from invoice lines;
 * paid/remaining are only mutated through payment-evidence review governance. No
 * secrets or gateway payloads stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_reference')->unique();
            $table->string('invoice_number')->unique();
            $table->foreignId('billing_account_id')->constrained('saas_billing_accounts')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('tenant_subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained('saas_billing_cycles')->nullOnDelete();
            $table->string('status')->default('DRAFT'); // DRAFT | ISSUED | PARTIAL | PAID | OVERDUE | DISPUTED | VOIDED | ARCHIVED
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency')->default('IDR');
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('remaining_amount', 14, 2)->default(0);
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('billing_account_id');
            $table->index('billing_cycle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_invoices');
    }
};
