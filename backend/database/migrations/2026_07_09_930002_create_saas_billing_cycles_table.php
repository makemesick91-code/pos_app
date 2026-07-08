<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing cycle. A governance period for grouping platform-to-
 * tenant billing invoices. Cycle transitions are conservative (DRAFT → OPEN →
 * LOCKED → CLOSED → ARCHIVED). No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_reference')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('DRAFT'); // DRAFT | OPEN | LOCKED | CLOSED | ARCHIVED
            $table->string('billing_month')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_cycles');
    }
};
