<?php

namespace App\Console\Commands;

use App\Services\TenantOnboarding\OnboardingException;
use App\Services\TenantOnboarding\OnboardingRequestData;
use App\Services\TenantOnboarding\TenantOnboardingService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:start. Starts or dry-runs a tenant onboarding.
 *
 * DEFAULT is dry-run (no mutation). Mutation requires BOTH --execute and
 * --idempotency-key (ONB-R004/R005). Output is redacted: it never prints an
 * owner password/token/email or any PII (ONB-R024).
 */
class OnboardingStartCommand extends Command
{
    protected $signature = 'onboarding:start
        {--execute : Perform the mutation (default is dry-run)}
        {--idempotency-key= : Required for --execute}
        {--plan=starter : Plan code}
        {--tenant-name=Demo UMKM : Tenant name}
        {--tenant-code= : Optional tenant code}
        {--owner-name=Owner : Owner name}
        {--owner-email= : Owner email (never printed)}
        {--owner-phone= : Owner phone (never printed)}
        {--branch-name=Pusat : First branch name}
        {--branch-code= : First branch code}
        {--with-trial : Activate trial (default on)}
        {--no-trial : Do not activate trial}
        {--with-cashier : Provision first cashier}
        {--with-register : Prepare device/register setup}
        {--with-invoice : Prepare trial-to-paid invoice}
        {--with-payment-intent : Prepare QRIS/mock payment intent}
        {--json : Output JSON}';

    protected $description = 'Start (or dry-run) a governed tenant onboarding run.';

    public function handle(TenantOnboardingService $service): int
    {
        $data = OnboardingRequestData::fromArray([
            'idempotency_key' => (string) ($this->option('idempotency-key') ?? ''),
            'plan_code' => (string) $this->option('plan'),
            'tenant_name' => (string) $this->option('tenant-name'),
            'tenant_code' => $this->option('tenant-code'),
            'owner_name' => (string) $this->option('owner-name'),
            'owner_email' => $this->option('owner-email'),
            'owner_phone' => $this->option('owner-phone'),
            'first_branch_name' => (string) $this->option('branch-name'),
            'first_branch_code' => $this->option('branch-code'),
            'with_trial' => ! $this->option('no-trial'),
            'with_cashier' => (bool) $this->option('with-cashier'),
            'with_register' => (bool) $this->option('with-register'),
            'with_invoice' => (bool) $this->option('with-invoice'),
            'with_payment_intent' => (bool) $this->option('with-payment-intent'),
            'onboarding_type' => 'platform_admin',
        ]);

        if (! $this->option('execute')) {
            try {
                $preview = $service->dryRun($data);
            } catch (OnboardingException $e) {
                return $this->reportError($e);
            }

            $this->emit(array_merge(['mode' => 'dry-run'], $preview));

            return self::SUCCESS;
        }

        if ((string) ($this->option('idempotency-key') ?? '') === '') {
            $this->error('--idempotency-key is required with --execute.');

            return self::FAILURE;
        }

        try {
            $run = $service->execute($data);
        } catch (OnboardingException $e) {
            return $this->reportError($e);
        }

        $this->emit([
            'mode' => 'execute',
            'run_id' => $run->id,
            'status' => $run->status,
            'tenant_id' => $run->tenant_id,
            'resolved_plan_code' => $run->resolved_plan_code,
            'trial_ends_at' => optional($run->trial_ends_at)?->toIso8601String(),
            'checklist_complete' => (bool) ($run->checklist_json['complete'] ?? false),
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $this->line($key.': '.(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value));
            }
        }
    }

    private function reportError(OnboardingException $e): int
    {
        $this->error('['.$e->reasonCode.'] '.$e->getMessage());

        return self::FAILURE;
    }
}
