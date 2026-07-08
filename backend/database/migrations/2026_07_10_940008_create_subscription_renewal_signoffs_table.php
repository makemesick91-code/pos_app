<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 24 — a subscription renewal sign-off. A rejected sign-off forces NO-GO;
 * an approved-with-risk sign-off forces WATCH. No secrets stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_signoffs', function (Blueprint $table) {
            $table->id();
            $table->string('signoff_reference')->unique();
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name')->nullable();
            $table->string('signer_role'); // OWNER | FINANCE | SALES | OPERATIONS | LEGAL_PRIVACY | TECHNICAL | SUPPORT
            $table->string('decision')->default('PENDING'); // APPROVED | APPROVED_WITH_RISK | REJECTED | PENDING
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
        Schema::dropIfExists('subscription_renewal_signoffs');
    }
};
