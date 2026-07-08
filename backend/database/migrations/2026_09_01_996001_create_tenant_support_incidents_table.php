<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 35 — tenant support incidents.
 *
 * Tracks a tenant support incident WITHOUT exposing PII/secrets. `title_safe`,
 * `summary_safe` and `metadata_json` are redacted/safe — they never store a
 * password, token, signature, phone, email, owner/customer name, address, NIK,
 * card, raw payload or any other PII (SUP-R007/R023). Every incident is
 * tenant-isolated (SUP-R003). See docs/architecture/sprint-35-*.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_support_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('incident_number')->unique();
            $table->string('category')->default('other');
            $table->string('severity')->default('low');
            $table->string('status')->default('open');
            $table->string('title_safe');
            $table->text('summary_safe')->nullable();
            $table->string('primary_reason_code')->nullable();
            $table->string('related_subject_type')->nullable();
            $table->unsignedBigInteger('related_subject_id')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'support_incidents_tenant_status_idx');
            $table->index(['category', 'severity'], 'support_incidents_category_severity_idx');
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_support_incidents');
    }
};
