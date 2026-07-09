<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_data_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('provisioning_run_id')->nullable()->constrained('tenant_provisioning_runs')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('import_type', 40);
            $table->string('source_format', 24)->default('csv');
            $table->string('status', 32)->default('draft');
            $table->string('mode', 16)->default('dry_run');
            $table->string('idempotency_key')->unique();
            $table->string('original_filename_hash')->nullable();
            $table->string('file_hash')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->boolean('rollback_supported')->default(true);
            $table->timestamp('rolled_back_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('summary_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['import_type', 'status']);
            $table->index('file_hash');
            $table->index('created_at');
        });

        Schema::create('tenant_data_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_data_import_run_id')->constrained('tenant_data_import_runs')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('row_type', 40);
            $table->string('row_fingerprint');
            $table->string('status', 32)->default('pending');
            $table->string('action', 16)->default('none');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message_safe')->nullable();
            $table->string('original_row_hash')->nullable();
            $table->json('normalized_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_data_import_run_id', 'row_number'], 'import_rows_run_row_unique');
            $table->unique(['tenant_data_import_run_id', 'row_fingerprint'], 'import_rows_run_fingerprint_unique');
            $table->index(['tenant_id', 'row_type', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('tenant_data_import_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_data_import_run_id')->nullable()->constrained('tenant_data_import_runs')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('artifact_type', 40);
            $table->string('storage_disk')->nullable();
            $table->string('storage_path_hash')->nullable();
            $table->string('file_hash')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'artifact_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_data_import_artifacts');
        Schema::dropIfExists('tenant_data_import_rows');
        Schema::dropIfExists('tenant_data_import_runs');
    }
};
