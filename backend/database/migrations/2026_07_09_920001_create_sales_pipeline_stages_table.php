<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 22 — sales pipeline stage definition. A canonical, ordered stage in the
 * sales pipeline (NEW → CONTACTED → QUALIFIED → … → WON_READY_FOR_ONBOARDING /
 * LOST / ARCHIVED). Stages are governance metadata only: a stage NEVER creates a
 * tenant/user/subscription/device and never bills. No secrets are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->string('stage_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE | INACTIVE | ARCHIVED
            $table->boolean('is_default')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_pipeline_stages');
    }
};
