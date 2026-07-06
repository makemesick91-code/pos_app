<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local/dev seed for Sprint 1: two active tenants, one store each, an owner and
 * a cashier per tenant, plus a platform SaaS admin.
 *
 * Default dev password: "password". FOR LOCAL/DEV/TESTING ONLY.
 */
class Sprint1TenantSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedTenant('TENANT-A', 'Tenant A', 'A1', 'ownerA@example.com', 'cashierA@example.com');
        $this->seedTenant('TENANT-B', 'Tenant B', 'B1', 'ownerB@example.com', 'cashierB@example.com');

        User::updateOrCreate(
            ['email' => 'admin@platform.test'],
            [
                'name' => 'SaaS Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SAAS_ADMIN,
                'tenant_id' => null,
                'store_id' => null,
                'is_active' => true,
            ],
        );
    }

    protected function seedTenant(
        string $code,
        string $name,
        string $storeCode,
        string $ownerEmail,
        string $cashierEmail,
    ): void {
        $tenant = Tenant::updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'status' => Tenant::STATUS_ACTIVE,
                'owner_name' => $name.' Owner',
                'subscription_plan' => 'STARTER',
                'subscription_status' => 'ACTIVE',
            ],
        );

        $store = Store::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $storeCode],
            [
                'name' => $name.' Store '.$storeCode,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => $name.' Owner',
                'password' => Hash::make('password'),
                'role' => User::ROLE_TENANT_OWNER,
                'tenant_id' => $tenant->id,
                'store_id' => null,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => $cashierEmail],
            [
                'name' => $name.' Cashier',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CASHIER,
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'is_active' => true,
            ],
        );
    }
}
