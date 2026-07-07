<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 18 — Pilot Closure & Production Handover Foundation.
 *
 * An append-only sign-off record against a production handover package. Each row
 * preserves the signer role, decision, timestamp, notes, and evidence reference.
 * Sign-off records are never deleted — a change of mind adds a new record.
 * A REJECTED sign-off forces NO_GO; APPROVED_WITH_RISK forces WATCH. No secret
 * or credential may be stored on the row.
 *
 * See docs/sprints/sprint-18-pilot-closure-production-handover-foundation.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_handover_signoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_handover_package_id')->constrained('production_handover_packages')->cascadeOnDelete();
            $table->string('signoff_reference')->unique();
            $table->foreignId('signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signer_name')->nullable();
            $table->string('signer_role'); // OWNER | ADMIN | OPERATOR | SUPPORT | TECHNICAL
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
        Schema::dropIfExists('production_handover_signoffs');
    }
};
