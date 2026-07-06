<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage for the User role/tenant helpers (no database).
 */
class UserRoleTest extends TestCase
{
    public function test_role_predicates_match_role(): void
    {
        $admin = new User(['role' => User::ROLE_SAAS_ADMIN]);
        $owner = new User(['role' => User::ROLE_TENANT_OWNER]);
        $storeAdmin = new User(['role' => User::ROLE_STORE_ADMIN]);
        $cashier = new User(['role' => User::ROLE_CASHIER]);

        $this->assertTrue($admin->isSaasAdmin());
        $this->assertTrue($owner->isTenantOwner());
        $this->assertTrue($storeAdmin->isStoreAdmin());
        $this->assertTrue($cashier->isCashier());

        $this->assertFalse($cashier->isSaasAdmin());
        $this->assertFalse($owner->isCashier());
    }

    public function test_belongs_to_tenant_compares_tenant_id(): void
    {
        $user = new User(['role' => User::ROLE_CASHIER, 'tenant_id' => 7]);

        $this->assertTrue($user->belongsToTenant(7));
        $this->assertFalse($user->belongsToTenant(8));
        $this->assertFalse($user->belongsToTenant(null));
    }
}
