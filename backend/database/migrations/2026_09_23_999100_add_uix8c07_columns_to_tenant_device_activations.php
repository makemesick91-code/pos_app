<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UIX-8C-07 — additive, reversible columns for the premium device-activation /
 * status / revocation experience (UIX8C-R218/R221/R234).
 *
 *  - app_version           : the client build reported at activation/heartbeat
 *                            (Settings "Application" section; support triage).
 *  - installation_id_hash  : sha256 of the app-generated installation id
 *                            (never IMEI/serial/MAC — UIX8C-R218). Stored as a
 *                            HASH only; the raw id never lands in a column.
 *  - revocation_reason     : a human-safe reason surfaced by the device-status
 *                            poll so a revoked device can explain itself
 *                            (UIX8C-R221/R234) without leaking secrets.
 *
 * No existing column is repurposed; the migration is fully reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_device_activations', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_device_activations', 'app_version')) {
                $table->string('app_version', 40)->nullable()->after('device_label');
            }
            if (! Schema::hasColumn('tenant_device_activations', 'installation_id_hash')) {
                $table->string('installation_id_hash', 128)->nullable()->after('app_version');
            }
            if (! Schema::hasColumn('tenant_device_activations', 'revocation_reason')) {
                $table->string('revocation_reason', 191)->nullable()->after('failure_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_device_activations', function (Blueprint $table): void {
            foreach (['app_version', 'installation_id_hash', 'revocation_reason'] as $column) {
                if (Schema::hasColumn('tenant_device_activations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
