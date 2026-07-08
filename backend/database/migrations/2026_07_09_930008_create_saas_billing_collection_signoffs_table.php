<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 23 — a SaaS billing collection sign-off. Append-only governance record.
 * A REJECTED sign-off forces NO-GO; an APPROVED_WITH_RISK sign-off forces WATCH.
 * No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_collection_signoffs', function (Blueprint $table) {
            $table->id();
            $table->string('signoff_reference')->unique();
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name')->nullable();
            $table->string('signer_role'); // OWNER | FINANCE | SALES | OPERATIONS | LEGAL_PRIVACY | TECHNICAL
            $table->string('decision'); // APPROVED | APPROVED_WITH_RISK | REJECTED | PENDING
            $table->text('notes')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('signer_role');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_collection_signoffs');
    }
};
