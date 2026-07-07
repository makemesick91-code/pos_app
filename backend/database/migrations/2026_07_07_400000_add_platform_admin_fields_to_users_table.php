<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 11 — Admin SaaS Control Panel Foundation.
 *
 * Marks a user as a platform administrator. Platform admins remain ordinary
 * authenticated users, but gain access to the cross-tenant admin SaaS APIs
 * (/api/v1/admin/*). A platform admin is NOT automatically a tenant business
 * user — being a platform admin never grants access to tenant business APIs.
 * There is no hardcoded admin email in code; the flag is set explicitly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('is_active');
            $table->timestamp('platform_admin_granted_at')->nullable()->after('is_platform_admin');
            $table->timestamp('platform_admin_revoked_at')->nullable()->after('platform_admin_granted_at');

            $table->index('is_platform_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_platform_admin']);
            $table->dropColumn([
                'is_platform_admin',
                'platform_admin_granted_at',
                'platform_admin_revoked_at',
            ]);
        });
    }
};
