<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing invoice line. line_total is calculated server-side
 * ((quantity * unit_amount) - discount + tax); no external billing provider is
 * ever called. No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('saas_billing_invoices')->cascadeOnDelete();
            $table->string('line_reference')->unique();
            $table->string('item_type'); // SUBSCRIPTION | DEVICE | SETUP | SUPPORT | ADJUSTMENT | DISCOUNT | OTHER
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('item_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_invoice_lines');
    }
};
