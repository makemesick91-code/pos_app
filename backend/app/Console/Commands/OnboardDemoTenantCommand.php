<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Onboarding\TenantOnboardingService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sprint 12 — CLI wrapper over TenantOnboardingService for creating a demo
 * tenant locally. Uses the exact same service as the admin API so it inherits
 * transaction safety and idempotency. It never sends real email/WhatsApp
 * invites and never performs billing. A temporary owner password is generated
 * when none is provided and printed once with a warning.
 */
class OnboardDemoTenantCommand extends Command
{
    protected $signature = 'tenants:onboard-demo
        {--reference= : Idempotent onboarding reference (generated when omitted)}
        {--tenant-name=Toko Demo Aish}
        {--tenant-code= : Unique tenant code (generated when omitted)}
        {--store-name=Toko Demo Pusat}
        {--owner-email= : Owner email (generated when omitted)}
        {--owner-name=Owner Demo}
        {--plan=starter : Subscription plan code}
        {--demo-data : Seed demo products/opening inventory}';

    protected $description = 'Onboard a demo tenant (default store, owner user, subscription, optional demo data) via the onboarding service.';

    public function handle(TenantOnboardingService $onboarding): int
    {
        $plan = SubscriptionPlan::query()->where('code', (string) $this->option('plan'))->first();

        if ($plan === null) {
            $this->error("Subscription plan '{$this->option('plan')}' not found. Seed plans first.");

            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        $reference = (string) ($this->option('reference') ?: 'cli-demo-'.Str::lower(Str::random(8)));
        $tenantCode = (string) ($this->option('tenant-code') ?: 'demo-'.Str::lower(Str::random(6)));
        $ownerEmail = (string) ($this->option('owner-email') ?: "owner.{$tenantCode}@example.test");
        $password = Str::random(16);

        $result = $onboarding->onboard($actor, [
            'onboarding_reference' => $reference,
            'tenant_name' => (string) $this->option('tenant-name'),
            'tenant_code' => $tenantCode,
            'store_name' => (string) $this->option('store-name'),
            'owner_name' => (string) $this->option('owner-name'),
            'owner_email' => $ownerEmail,
            'owner_password' => $password,
            'subscription_plan_id' => $plan->id,
            'subscription_status' => TenantSubscription::STATUS_TRIAL,
            'trial_days' => 14,
            'demo_data_enabled' => (bool) $this->option('demo-data'),
        ]);

        $run = $result['run'];

        $this->info($result['replay']
            ? "Onboarding reference '{$reference}' already exists — replayed run #{$run->id} (status {$run->status})."
            : "Onboarded tenant #{$run->tenant_id} via run #{$run->id} (status {$run->status}).");

        if (! $result['replay']) {
            $this->line("  Owner email: {$ownerEmail}");
            $this->warn("  Temporary owner password (shown once, change immediately): {$password}");
        }

        return self::SUCCESS;
    }

    /**
     * A platform admin actor for the CLI context. Reuses an existing platform
     * admin when present; otherwise provisions a controlled system admin user.
     */
    private function resolveActor(): User
    {
        $admin = User::query()->where('is_platform_admin', true)->first();

        if ($admin !== null) {
            return $admin;
        }

        return User::query()->create([
            'name' => 'System Onboarding Admin',
            'email' => 'system-onboarding@platform.local',
            'password' => Str::random(24),
            'role' => User::ROLE_SAAS_ADMIN,
            'is_active' => true,
            'is_platform_admin' => true,
            'platform_admin_granted_at' => now(),
        ]);
    }
}
