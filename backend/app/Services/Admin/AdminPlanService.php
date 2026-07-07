<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Sprint 11 — platform-admin subscription plan administration.
 *
 * Creates, updates, and deactivates plans (the Sprint 10 SubscriptionPlan
 * foundation). There is no hard delete: plans are only deactivated so existing
 * tenant subscriptions retain their historical plan reference. Every mutation is
 * audit-logged.
 */
class AdminPlanService
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    public function list(?bool $activeOnly = null): Collection
    {
        $query = SubscriptionPlan::query()->orderBy('id');

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data, ?Request $request = null): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_monthly' => $data['price_monthly'] ?? 0,
            'max_stores' => $data['max_stores'],
            'max_devices' => $data['max_devices'],
            'max_products' => $data['max_products'] ?? null,
            'features' => $data['features'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_PLAN_CREATED,
            targetType: AdminAuditLog::TARGET_PLAN,
            targetId: $plan->id,
            before: null,
            after: $this->snapshot($plan),
            request: $request,
        );

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, SubscriptionPlan $plan, array $data, ?Request $request = null): SubscriptionPlan
    {
        $before = $this->snapshot($plan);

        foreach (['code', 'name', 'description', 'price_monthly', 'max_stores', 'max_devices', 'max_products', 'features', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $plan->{$field} = $data[$field];
            }
        }

        $plan->save();

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_PLAN_UPDATED,
            targetType: AdminAuditLog::TARGET_PLAN,
            targetId: $plan->id,
            before: $before,
            after: $this->snapshot($plan->fresh()),
            request: $request,
        );

        return $plan->refresh();
    }

    public function deactivate(User $actor, SubscriptionPlan $plan, ?Request $request = null): SubscriptionPlan
    {
        $before = $this->snapshot($plan);

        if ($plan->is_active) {
            $plan->is_active = false;
            $plan->save();
        }

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_PLAN_DEACTIVATED,
            targetType: AdminAuditLog::TARGET_PLAN,
            targetId: $plan->id,
            before: $before,
            after: $this->snapshot($plan->fresh()),
            request: $request,
        );

        return $plan->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price_monthly' => (string) $plan->price_monthly,
            'max_stores' => (int) $plan->max_stores,
            'max_devices' => (int) $plan->max_devices,
            'max_products' => $plan->max_products,
            'is_active' => (bool) $plan->is_active,
        ];
    }
}
