<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 22 — a sales lead assignment history row. Preserves who was assigned a
 * lead, by whom, and when it was reassigned/unassigned. Assignment is internal
 * sales ownership only — it never provisions anything and never bills.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_reference')->unique();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('ACTIVE'); // ACTIVE | REASSIGNED | UNASSIGNED | ARCHIVED
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sales_lead_id');
            $table->index('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_lead_assignments');
    }
};
