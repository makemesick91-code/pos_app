<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 21 — a versioned landing page content record. CTA targets may point to
 * the interest form only — never account creation. Package highlights must align
 * with the commercial package catalog and must not promise real billing beyond
 * the approved commercial docs. No secrets, no live tracking tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_reference')->unique();
            $table->string('status')->default('DRAFT'); // DRAFT | REVIEW | APPROVED | PUBLISHED | ARCHIVED | BLOCKED
            $table->string('headline');
            $table->string('subheadline')->nullable();
            $table->string('hero_cta_label')->nullable();
            $table->string('hero_cta_target')->nullable();
            $table->json('target_segments')->nullable();
            $table->json('package_highlights')->nullable();
            $table->json('feature_highlights')->nullable();
            $table->json('proof_points')->nullable();
            $table->json('faq_items')->nullable();
            $table->json('seo_summary')->nullable();
            $table->json('privacy_summary')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_versions');
    }
};
