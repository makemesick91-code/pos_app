<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7 — Offline Cash & Sync Foundation.
 *
 * Offline CASH sales are created on the Android device before they can reach the
 * server. When the device comes back online it replays the queued sales; the
 * client-generated reference makes that replay idempotent so a retried request
 * can never mint a second invoice for the same physical transaction.
 *
 * - client_reference : UUID generated on the device; unique per (tenant, store).
 * - client_created_at: when the sale was rung up on the device (may predate sync).
 * - synced_at        : when the server accepted/first stored the offline sale.
 *
 * See foundation sections 14 (Offline & Sync) and 16 (Security).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('client_reference', 191)->nullable()->after('source');
            $table->timestamp('client_created_at')->nullable()->after('client_reference');
            $table->timestamp('synced_at')->nullable()->after('client_created_at');

            // Idempotency guard. NULLs are treated as distinct by SQLite/MySQL, so
            // online sales (no client_reference) are never blocked by this unique.
            $table->unique(['tenant_id', 'store_id', 'client_reference'], 'sales_tenant_store_client_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_tenant_store_client_reference_unique');
            $table->dropColumn(['client_reference', 'client_created_at', 'synced_at']);
        });
    }
};
