<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 21 — a preserved public website sign-off record. A REJECTED signoff
 * forces NO-GO; an APPROVED_WITH_RISK signoff forces WATCH. Records are never
 * deleted and never carry secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_website_signoffs', function (Blueprint $table) {
            $table->id();
            $table->string('signoff_reference')->unique();
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name')->nullable();
            $table->string('signer_role'); // OWNER | TECHNICAL | SALES | OPERATIONS | LEGAL_PRIVACY
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
        Schema::dropIfExists('public_website_signoffs');
    }
};
