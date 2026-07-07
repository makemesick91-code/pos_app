<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sprint 11 — platform-admin controlled subscription assignment/update.
 *
 * This manages the subscription foundation only: it assigns a plan and sets the
 * status/date window for a tenant. It never collects real billing, charges
 * money, or creates an invoice — that is out of scope for Sprint 11. Every
 * mutation is audit-logged with sanitized before/after snapshots.
 */
class AdminSubscriptionService
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * Assign a plan to a tenant by creating a new current subscription row.
     *
     * @param  array<string, mixed>  $data
     */
    public function assign(User $actor, Tenant $tenant, array $data, ?Request $request = null): TenantSubscription
    {
        $plan = SubscriptionPlan::query()->findOrFail((int) $data['subscription_plan_id']);

        $subscription = DB::transaction(function () use ($tenant, $plan, $data) {
            return TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'status' => $data['status'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'trial_ends_at' => $data['trial_ends_at'] ?? null,
                'grace_ends_at' => $data['grace_ends_at'] ?? null,
            ]);
        });

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_SUBSCRIPTION_ASSIGNED,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION,
            targetId: $subscription->id,
            tenantId: $tenant->id,
            before: null,
            after: $this->snapshot($subscription),
            metadata: ['plan_code' => $plan->code, 'notes' => $data['notes'] ?? null],
            request: $request,
        );

        return $subscription;
    }

    /**
     * Update an existing tenant subscription's status/date window.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(
        User $actor,
        Tenant $tenant,
        TenantSubscription $subscription,
        array $data,
        ?Request $request = null,
    ): TenantSubscription {
        if ((int) $subscription->tenant_id !== (int) $tenant->id) {
            throw new RuntimeException('Subscription does not belong to tenant.');
        }

        $before = $this->snapshot($subscription);

        DB::transaction(function () use ($subscription, $data): void {
            foreach (['status', 'starts_at', 'ends_at', 'trial_ends_at', 'grace_ends_at'] as $field) {
                if (array_key_exists($field, $data)) {
                    $subscription->{$field} = $data[$field];
                }
            }

            $subscription->save();
        });

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_SUBSCRIPTION_UPDATED,
            targetType: AdminAuditLog::TARGET_SUBSCRIPTION,
            targetId: $subscription->id,
            tenantId: $tenant->id,
            before: $before,
            after: $this->snapshot($subscription->fresh()),
            metadata: ['notes' => $data['notes'] ?? null],
            request: $request,
        );

        return $subscription->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TenantSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'status' => $subscription->status,
            'starts_at' => optional($subscription->starts_at)->toIso8601String(),
            'ends_at' => optional($subscription->ends_at)->toIso8601String(),
            'trial_ends_at' => optional($subscription->trial_ends_at)->toIso8601String(),
            'grace_ends_at' => optional($subscription->grace_ends_at)->toIso8601String(),
        ];
    }
}
