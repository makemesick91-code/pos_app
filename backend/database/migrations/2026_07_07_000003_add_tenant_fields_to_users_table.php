<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add SaaS tenant/store/role foundation to the existing users table.
 * Roles: saas_admin | tenant_owner | store_admin | cashier.
 * See foundation section 8 & 16.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained('tenants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->after('tenant_id')
                ->constrained('stores')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('cashier')->after('phone');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index(['tenant_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['store_id']);
            $table->dropIndex(['tenant_id', 'store_id']);
            $table->dropColumn(['tenant_id', 'store_id', 'phone', 'role', 'is_active', 'last_login_at']);
        });
    }
};
