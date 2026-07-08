<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 35 — redacted support incident notes (SUP-R023).
 *
 * `body_safe` and `metadata_json` are redacted before persistence; a note never
 * stores raw secrets/PII. Notes are tenant-isolated (a denormalised `tenant_id`
 * keeps every isolation query cheap and unambiguous). See docs/architecture/sprint-35-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_support_incident_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_support_incident_id')->constrained('tenant_support_incidents')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note_type')->default('internal');
            $table->text('body_safe');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('tenant_support_incident_id', 'support_notes_incident_idx');
            $table->index('tenant_id', 'support_notes_tenant_idx');
            $table->index('note_type', 'support_notes_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_support_incident_notes');
    }
};
