<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 21 — a governed public website page (HOME/PACKAGES/PRIVACY/TERMS/
 * THANK_YOU). Content is governance metadata only. Page content must not contain
 * secrets, must not expose internal admin URLs, and package/pricing content must
 * be aligned with the commercial package catalog. No secrets are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_website_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->unique(); // HOME | PACKAGES | PRIVACY | TERMS | THANK_YOU
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | APPROVED | PUBLISHED | ARCHIVED | BLOCKED
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('content_sections')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_website_pages');
    }
};
