<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing manual payment evidence. Manual evidence only:
 * MANUAL_QRIS_REFERENCE is a label, never a QRIS API call. An ACCEPTED evidence
 * updates the invoice paid/remaining state through governance; a REJECTED evidence
 * never updates paid_amount. No payment gateway payloads or secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_payment_evidences', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference')->unique();
            $table->foreignId('invoice_id')->constrained('saas_billing_invoices')->cascadeOnDelete();
            $table->string('status')->default('SUBMITTED'); // SUBMITTED | UNDER_REVIEW | ACCEPTED | REJECTED | VOIDED
            $table->string('payment_method'); // BANK_TRANSFER | CASH_DEPOSIT | MANUAL_QRIS_REFERENCE | OTHER_MANUAL
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->string('evidence_label')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_payment_evidences');
    }
};
