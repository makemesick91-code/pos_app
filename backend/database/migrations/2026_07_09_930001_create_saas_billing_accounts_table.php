<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing account. Platform-to-tenant billing governance
 * record. A billing account MAY reference a tenant but creating one NEVER creates
 * a tenant and a status change NEVER suspends tenant access. This is NOT a POS
 * cashier/customer payment record. No secrets or payment gateway credentials are
 * stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('account_reference')->unique();
            $table->string('billing_name');
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_identifier')->nullable();
            $table->string('status')->default('ACTIVE'); // ACTIVE | ON_HOLD | SUSPENDED_MANUAL_REVIEW | ARCHIVED
            $table->string('billing_currency')->default('IDR');
            $table->integer('payment_terms_days')->default(7);
            $table->foreignId('collection_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_accounts');
    }
};
