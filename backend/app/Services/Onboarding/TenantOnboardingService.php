<?php

namespace App\Services\Onboarding;

use App\Models\AdminAuditLog;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantOnboardingRun;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sprint 12 — orchestrates platform-admin-driven tenant onboarding.
 *
 * A single request creates a tenant, its default store, an owner (tenant_owner)
 * user, and an initial subscription inside one DB transaction, then optionally
 * seeds demo data. It is idempotent by `onboarding_reference`: a replayed
 * reference returns the existing run and never creates a second tenant/store/
 * user/subscription. The owner password is write-only — it is hashed and never
 * stored in the run metadata/checklist or the audit log. No real billing, no
 * email/WhatsApp invites, no impersonation. See Sprint 12 evidence.
 */
class TenantOnboardingService
{
    public function __construct(
        private readonly DemoDataSeederService $seeder,
        private readonly TenantOnboardingChecklistService $checklist,
        private readonly AdminAuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{run: TenantOnboardingRun, replay: bool}
     */
    public function onboard(User $actor, array $data, ?Request $request = null): array
    {
        $reference = (string) $data['onboarding_reference'];

        $existing = TenantOnboardingRun::query()
            ->where('onboarding_reference', $reference)
            ->first();

        if ($existing !== null) {
            // Idempotent replay: never create a second tenant for the same
            // reference, regardless of the existing run's status.
            $this->audit->log(
                actor: $actor,
                action: AdminAuditLog::ACTION_TENANT_ONBOARDING_REPLAYED,
                targetType: AdminAuditLog::TARGET_ONBOARDING_RUN,
                targetId: $existing->id,
                tenantId: $existing->tenant_id,
                metadata: ['onboarding_reference' => $reference, 'status' => $existing->status],
                request: $request,
            );

            return ['run' => $existing, 'replay' => true];
        }

        $run = TenantOnboardingRun::query()->create([
            'onboarding_reference' => $reference,
            'requested_by' => $actor->id,
            'status' => TenantOnboardingRun::STATUS_PENDING,
            'tenant_name' => (string) $data['tenant_name'],
            'store_name' => $data['store_name'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'owner_email' => $data['owner_email'] ?? null,
            'demo_data_enabled' => (bool) ($data['demo_data_enabled'] ?? false),
        ]);

        $run->markRunning();

        try {
            $this->runOnboarding($run, $data);
        } catch (Throwable $e) {
            $run->markFailed($e);

            throw $e;
        }

        $this->audit->log(
            actor: $actor,
            action: AdminAuditLog::ACTION_TENANT_ONBOARDED,
            targetType: AdminAuditLog::TARGET_ONBOARDING_RUN,
            targetId: $run->id,
            tenantId: $run->tenant_id,
            metadata: [
                'onboarding_reference' => $reference,
                'tenant_id' => $run->tenant_id,
                'default_store_id' => $run->default_store_id,
                'owner_user_id' => $run->owner_user_id,
                'demo_data_enabled' => $run->demo_data_enabled,
            ],
            request: $request,
        );

        return ['run' => $run->refresh(), 'replay' => false];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function runOnboarding(TenantOnboardingRun $run, array $data): void
    {
        DB::transaction(function () use ($run, $data): void {
            $plan = SubscriptionPlan::query()->findOrFail((int) $data['subscription_plan_id']);

            $tenant = Tenant::query()->create([
                'code' => (string) $data['tenant_code'],
                'name' => (string) $data['tenant_name'],
                'business_type' => $data['business_type'] ?? null,
                'owner_name' => $data['owner_name'] ?? null,
                'owner_phone' => $data['owner_phone'] ?? null,
                'status' => Tenant::STATUS_ACTIVE,
                'subscription_plan' => $plan->code,
                'subscription_status' => (string) ($data['subscription_status'] ?? TenantSubscription::STATUS_TRIAL),
            ]);

            $store = Store::query()->create([
                'tenant_id' => $tenant->id,
                'name' => (string) ($data['store_name'] ?? $tenant->name.' Pusat'),
                'code' => $this->storeCode((string) $data['tenant_code']),
                'is_active' => true,
            ]);

            $owner = User::query()->create([
                'name' => (string) ($data['owner_name'] ?? 'Owner'),
                'email' => (string) $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => Hash::make((string) $data['owner_password']),
                'tenant_id' => $tenant->id,
                'store_id' => $store->id,
                'role' => User::ROLE_TENANT_OWNER,
                'is_active' => true,
            ]);

            $subscription = $this->assignSubscription($tenant, $plan, $data);

            $metadata = [];

            if ($run->demo_data_enabled) {
                $result = $this->seeder->seed($tenant, $store, [
                    'seed_products' => true,
                    'seed_opening_inventory' => true,
                    'seed_demo_sales' => false,
                ]);
                $metadata['demo_manifest'] = $result['manifest'];
                $metadata['demo_notes'] = $result['notes'];
                $run->demo_data_seeded_at = Carbon::now();
            }

            $run->tenant_id = $tenant->id;
            $run->default_store_id = $store->id;
            $run->owner_user_id = $owner->id;
            $run->subscription_plan_id = $plan->id;
            $run->tenant_subscription_id = $subscription->id;
            $run->metadata = $metadata;
            $run->save();

            $checklist = $this->checklist->buildForTenant($tenant->refresh());
            $run->markCompleted($checklist);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assignSubscription(Tenant $tenant, SubscriptionPlan $plan, array $data): TenantSubscription
    {
        $status = (string) ($data['subscription_status'] ?? TenantSubscription::STATUS_TRIAL);
        $now = Carbon::now();
        $trialDays = (int) ($data['trial_days'] ?? 14);

        $attributes = [
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'starts_at' => $now,
            'ends_at' => null,
            'trial_ends_at' => null,
        ];

        if ($status === TenantSubscription::STATUS_TRIAL) {
            $attributes['trial_ends_at'] = $now->copy()->addDays(max(1, $trialDays));
        } elseif ($status === TenantSubscription::STATUS_ACTIVE) {
            $attributes['ends_at'] = $now->copy()->addMonth();
        }

        return TenantSubscription::query()->create($attributes);
    }

    private function storeCode(string $tenantCode): string
    {
        return strtoupper(Str::slug($tenantCode)).'-01';
    }
}
