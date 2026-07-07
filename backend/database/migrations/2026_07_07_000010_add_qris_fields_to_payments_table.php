<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 5 — QRIS display columns for the payments table.
 *
 * All columns are nullable so the Sprint 4 CASH flow (which never populates
 * them) is untouched. These hold only what is safe to show a cashier/customer:
 * the QR payload/text, an optional hosted QR image or payment URL, and a small
 * provider-agnostic metadata bag. Provider secrets are never stored here — the
 * raw gateway response continues to live in the hidden `raw_response` column.
 * See foundation sections 13 and 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('qr_payload')->nullable()->after('provider_reference');
            $table->string('qr_image_url')->nullable()->after('qr_payload');
            $table->string('payment_url')->nullable()->after('qr_image_url');
            $table->json('metadata')->nullable()->after('payment_url');

            $table->index(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider', 'provider_reference']);
            $table->dropColumn(['qr_payload', 'qr_image_url', 'payment_url', 'metadata']);
        });
    }
};
