<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registered devices (Sprint 10). A tenant-owned record of an Android device
 * allowed to consume protected business APIs. The device_uuid is generated and
 * stored locally on the device (no password / payment credential, no invasive
 * hardware fingerprint). Registration and the active-device count are backend
 * authoritative and capped by the tenant's plan max_devices. A revoked/blocked
 * device does not count as active and cannot access protected APIs. The
 * composite unique index guarantees one row per (tenant_id, device_uuid); the
 * same device_uuid may exist under different tenants without collision. See
 * foundation sections 8, 9, 16 and Sprint 10 evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registered_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('device_uuid', 191);
            $table->string('device_name')->nullable();
            $table->string('platform', 20)->default('ANDROID');
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'device_uuid'], 'registered_devices_tenant_uuid_unique');
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registered_devices');
    }
};
