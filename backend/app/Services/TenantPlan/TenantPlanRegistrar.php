<?php

namespace App\Services\TenantPlan;

use App\Models\PlanEntitlement;
use App\Models\PlanUsageLimit;
use App\Models\TenantPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — syncs the canonical plan definition in config/tenant_plan.php into
 * the persisted tenant_plans / plan_entitlements / plan_usage_limits tables (the
 * server-side source of truth, TPE-R001).
 *
 * sync() is idempotent (upsert). ensure() lazily syncs once per process if the
 * catalogue is empty so plan resolution always has a populated source of truth,
 * without requiring every seeder/test to wire it manually. This registrar is the
 * only writer of the catalogue tables; nothing is created from client input.
 */
class TenantPlanRegistrar
{
    public function ensure(): void
    {
        if (! Schema::hasTable('tenant_plans')) {
            return;
        }

        // Idempotent lazy sync. Deliberately re-checks the catalogue each call
        // (rather than caching in a static) so it stays correct under test
        // transaction rollbacks (RefreshDatabase) as well as in production. The
        // guard is a single cheap COUNT and sync only runs when the catalogue is
        // actually empty.
        if (TenantPlan::query()->count() === 0) {
            $this->sync();
        }
    }

    /**
     * Upsert every plan and its entitlement/limit rows from config.
     */
    public function sync(): void
    {
        $plans = (array) config('tenant_plan.plans', []);
        $limitMeta = (array) config('tenant_plan.usage_limits', []);

        DB::transaction(function () use ($plans, $limitMeta): void {
            foreach ($plans as $key => $definition) {
                $plan = TenantPlan::query()->updateOrCreate(
                    ['key' => (string) $key],
                    [
                        'name' => (string) ($definition['name'] ?? ucfirst((string) $key)),
                        'description' => $definition['description'] ?? null,
                        'status' => TenantPlan::STATUS_ACTIVE,
                        'billing_interval' => $definition['billing_interval'] ?? null,
                    ],
                );

                foreach ((array) ($definition['entitlements'] ?? []) as $entitlementKey => $enabled) {
                    PlanEntitlement::query()->updateOrCreate(
                        ['tenant_plan_id' => $plan->id, 'entitlement_key' => (string) $entitlementKey],
                        ['enabled' => (bool) $enabled],
                    );
                }

                foreach ((array) ($definition['limits'] ?? []) as $limitKey => $limitDef) {
                    PlanUsageLimit::query()->updateOrCreate(
                        ['tenant_plan_id' => $plan->id, 'limit_key' => (string) $limitKey],
                        [
                            'limit_value' => array_key_exists('limit', (array) $limitDef) ? (int) $limitDef['limit'] : null,
                            'unlimited' => (bool) (($limitDef['unlimited'] ?? false)),
                            'period' => (string) ($limitMeta[$limitKey]['period'] ?? PlanUsageLimit::PERIOD_LIFETIME),
                        ],
                    );
                }
            }
        });
    }
}
