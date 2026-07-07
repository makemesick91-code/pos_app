<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 20 — commercial launch signoff. A preserved sign-off record for a
 * commercial launch run: signer role, decision, notes and evidence reference.
 *
 * A REJECTED signoff forces NO-GO; an APPROVED_WITH_RISK signoff forces WATCH.
 * Signoff records are never deleted and never carry secrets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_launch_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_launch_run_id')->nullable()->constrained('commercial_launch_runs')->nullOnDelete();
            $table->string('signoff_reference')->unique();
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name')->nullable();
            $table->string('signer_role'); // OWNER | TECHNICAL | SUPPORT | SALES | OPERATIONS
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
        Schema::dropIfExists('commercial_launch_signoffs');
    }
};
