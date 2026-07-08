<?php

namespace App\Services\TenantOnboarding;

use App\Models\Tenant;
use App\Models\TenantProvisioningRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sprint 33 — the SINGLE central orchestrator for tenant onboarding (ONB-R001).
 *
 * A single request creates a tenant, activates a governed trial, provisions the
 * first branch, owner/admin and cashier, prepares the first register/device
 * setup, seeds safe defaults and (optionally) prepares the trial-to-paid invoice
 * and payment intent — each step enforcing the Sprint 32 entitlement gate and
 * each mutation audited to the provisioning trace.
 *
 * Transactional + idempotent (ONB-R004/R021): resource mutations run inside one
 * DB transaction that rolls back on any failure, so there is never a half-created
 * tenant. The run row + a single failed-step row are written OUTSIDE the
 * transaction so a failure always leaves an auditable failed state (ONB-R020).
 * Idempotent by `idempotency_key`: a replayed key resumes/returns the run and
 * never creates a second tenant/branch/user/register/device.
 */
class TenantOnboardingService
{
    public function __construct(
        private readonly TenantProvisioningService $tenants,
        private readonly TrialActivationService $trials,
        private readonly FirstBranchProvisioningService $branches,
        private readonly OwnerAdminProvisioningService $owners,
        private readonly CashierProvisioningService $cashiers,
        private readonly DeviceRegisterProvisioningService $devices,
        private readonly TenantSeedDataService $seed,
        private readonly TrialToPaidReadinessService $readiness,
        private readonly OnboardingChecklistService $checklist,
        private readonly OnboardingAuditService $audit,
    ) {}

    /**
     * Validate + preview an onboarding request WITHOUT any mutation (ONB-R004).
     *
     * @return array<string, mixed>
     */
    public function dryRun(OnboardingRequestData $data): array
    {
        $this->validate($data, requireIdempotency: false);

        return [
            'dry_run' => true,
            'would_create' => true,
            'plan_code' => $data->planCode,
            'onboarding_type' => $data->onboardingType,
            'with_trial' => $data->withTrial,
            'trial_duration_days' => (int) config('onboarding_governance.trial.default_duration_days', 14),
            'steps' => $this->plannedSteps($data),
        ];
    }

    /**
     * Start (or resume/replay) an onboarding run and provision the tenant.
     */
    public function execute(OnboardingRequestData $data, ?User $actor = null): TenantProvisioningRun
    {
        $this->validate($data);

        $run = $this->findOrCreateRun($data, $actor);

        // Idempotent replay: a completed run is returned untouched (ONB-R021).
        if ($run->status === TenantProvisioningRun::STATUS_COMPLETED) {
            return $run;
        }

        if ($run->status === TenantProvisioningRun::STATUS_CANCELLED) {
            throw new OnboardingException('RUN_CANCELLED', 'Onboarding run was cancelled and cannot be resumed.');
        }

        $run->status = TenantProvisioningRun::STATUS_PROVISIONING;
        $run->started_at = $run->started_at ?? Carbon::now();
        $run->failure_reason = null;
        $run->failed_at = null;
        $run->save();

        try {
            DB::transaction(function () use ($run, $data, $actor): void {
                $this->provision($run, $data, $actor);
            });
        } catch (OnboardingException $e) {
            $this->fail($run, $data, $e->reasonCode, $e->getMessage());

            throw $e;
        } catch (Throwable $e) {
            $this->fail($run, $data, 'FAILED', $e->getMessage());

            throw $e;
        }

        return $run->refresh();
    }

    /**
     * The provisioning pipeline. Runs inside the caller's DB transaction so any
     * failure rolls back every resource mutation.
     */
    private function provision(TenantProvisioningRun $run, OnboardingRequestData $data, ?User $actor): void
    {
        // 1. resolve_plan
        $step = $this->audit->startStep($run, 'resolve_plan', ['plan_code' => $data->planCode]);
        $run->resolved_plan_code = $data->planCode;
        $run->save();
        $this->audit->completeStep($step, null, null, ['resolved_plan_code' => $data->planCode]);

        // 2. create_tenant
        $step = $this->audit->startStep($run, 'create_tenant');
        $result = $this->tenants->provision($run, $data);
        $tenant = $result['tenant'];
        $run->tenant_id = $tenant->id;
        $run->save();
        $this->audit->completeStep($step, Tenant::class, (int) $tenant->id, ['created' => $result['created']]);

        // 3. activate_trial
        $step = $this->audit->startStep($run, 'activate_trial', ['with_trial' => $data->withTrial]);
        $trial = $this->trials->activate($tenant, $data);
        $subscription = $trial['subscription'];
        $run->trial_starts_at = $subscription->starts_at;
        $run->trial_ends_at = $subscription->trial_ends_at;
        $run->status = TenantProvisioningRun::STATUS_TRIAL_ACTIVE;
        $run->save();
        $this->audit->completeStep($step, get_class($subscription), (int) $subscription->id, [
            'status' => $subscription->status,
            'trial' => $trial['trial'],
        ]);

        // 4. provision_first_branch
        $step = $this->audit->startStep($run, 'provision_first_branch');
        $branch = $this->branches->provision($tenant, $data, $actor);
        $run->first_branch_id = $branch->id;
        $run->save();
        $this->audit->completeStep($step, get_class($branch), (int) $branch->id);

        // 5. provision_owner_admin
        $step = $this->audit->startStep($run, 'provision_owner_admin');
        $owner = $this->owners->provision($tenant, $branch, $data, $actor);
        $run->owner_user_id = $owner->id;
        $run->save();
        $this->audit->completeStep($step, User::class, (int) $owner->id, ['role' => $owner->role]);

        // 6. provision_first_cashier
        $step = $this->audit->startStep($run, 'provision_first_cashier');
        if ($data->withCashier) {
            $cashier = $this->cashiers->provision($tenant, $branch, $data, $actor);
            $run->first_cashier_user_id = $cashier->id;
            $run->save();
            $this->audit->completeStep($step, User::class, (int) $cashier->id, ['role' => $cashier->role]);
        } else {
            $this->audit->skipStep($step, 'SKIPPED_REQUEST');
        }

        // 7. prepare_device_register
        $step = $this->audit->startStep($run, 'prepare_device_register');
        if ($data->withRegister) {
            $prepared = $this->devices->prepare($tenant, $branch, $actor);
            $run->first_register_id = $prepared['register_id'];
            $run->save();
            $this->audit->completeStep($step, null, null, [
                'register_id' => $prepared['register_id'],
                'setup_fingerprint' => $prepared['setup_fingerprint'],
            ]);
        } else {
            $this->audit->skipStep($step, 'SKIPPED_REQUEST');
        }

        // 8. seed_default_data
        $step = $this->audit->startStep($run, 'seed_default_data');
        $seedResult = $this->seed->seed($tenant, $branch);
        $this->audit->completeStep($step, null, null, $seedResult);

        // 9. prepare_invoice
        $step = $this->audit->startStep($run, 'prepare_invoice', ['with_invoice' => $data->withInvoice]);
        $invoice = null;
        if ($data->withInvoice) {
            $invoice = $this->readiness->generateInvoice($tenant, $actor);
            $run->tenant_billing_invoice_id = $invoice->id;
            $run->billing_period = $invoice->period_key;
            $run->status = TenantProvisioningRun::STATUS_WAITING_PAYMENT;
            $run->save();
            $this->audit->completeStep($step, get_class($invoice), (int) $invoice->id, $this->readiness->summary($invoice));
        } else {
            $this->audit->skipStep($step, 'SKIPPED_REQUEST');
        }

        // 10. prepare_payment_intent
        $step = $this->audit->startStep($run, 'prepare_payment_intent', ['with_payment_intent' => $data->withPaymentIntent]);
        if ($data->withPaymentIntent && $invoice !== null) {
            $intent = $this->readiness->createPaymentIntent($invoice, $actor, $run->idempotency_key.':intent');
            $run->payment_intent_id = $intent->id;
            $run->save();
            $this->audit->completeStep($step, get_class($intent), (int) $intent->id, [
                'intent_status' => $intent->status,
            ]);
        } else {
            $this->audit->skipStep($step, $data->withPaymentIntent ? 'SKIPPED_REQUEST' : 'SKIPPED_REQUEST');
        }

        // 11. finalize
        $step = $this->audit->startStep($run, 'finalize');
        $checklist = $this->checklist->build($run->refresh());
        $run->checklist_json = $checklist;
        $run->status = $checklist['complete']
            ? TenantProvisioningRun::STATUS_COMPLETED
            : TenantProvisioningRun::STATUS_TRIAL_ACTIVE;
        $run->completed_at = $checklist['complete'] ? Carbon::now() : null;
        $run->save();
        $this->audit->completeStep($step, null, null, ['complete' => $checklist['complete']]);
    }

    /**
     * Cancel a run when it is in a safe state (ONB-R019). A completed run is
     * never cancelled; provisioned resources are left intact and auditable.
     */
    public function cancel(TenantProvisioningRun $run, ?string $reason = null): TenantProvisioningRun
    {
        if (! $run->isCancellable()) {
            throw new OnboardingException('CANCEL_NOT_ALLOWED', "Run in status '{$run->status}' cannot be cancelled.");
        }

        $run->status = TenantProvisioningRun::STATUS_CANCELLED;
        $run->cancelled_at = Carbon::now();
        if ($reason !== null && $reason !== '') {
            $run->failure_reason = mb_substr($reason, 0, 400);
        }
        $run->save();

        $step = $this->audit->startStep($run, 'finalize', ['action' => 'cancel']);
        $this->audit->skipStep($step, 'SKIPPED_REQUEST', ['cancelled' => true]);

        return $run;
    }

    private function fail(TenantProvisioningRun $run, OnboardingRequestData $data, string $reasonCode, string $message): void
    {
        // Written OUTSIDE the rolled-back transaction so the failed state persists.
        $failedStep = $this->currentStepGuess($run, $data);
        $this->audit->recordFailedStep($run, $failedStep, $reasonCode, $message, ['reason_code' => $reasonCode]);

        $run->refresh();
        $run->markFailed('['.$reasonCode.'] '.mb_substr($message, 0, 400));
    }

    /**
     * Best-effort attribution of which step failed, for the auditable failed
     * state. Deterministic: the first not-completed planned step.
     */
    private function currentStepGuess(TenantProvisioningRun $run, OnboardingRequestData $data): string
    {
        $completed = $run->steps()
            ->where('status', \App\Models\TenantProvisioningStep::STATUS_COMPLETED)
            ->pluck('step_key')
            ->all();

        foreach ($this->plannedSteps($data) as $stepKey) {
            if (! in_array($stepKey, $completed, true)) {
                return $stepKey;
            }
        }

        return 'finalize';
    }

    private function findOrCreateRun(OnboardingRequestData $data, ?User $actor): TenantProvisioningRun
    {
        $existing = TenantProvisioningRun::query()
            ->where('idempotency_key', $data->idempotencyKey)
            ->first();

        if ($existing instanceof TenantProvisioningRun) {
            return $existing;
        }

        return TenantProvisioningRun::query()->create([
            'requested_plan_code' => $data->planCode,
            'onboarding_type' => $data->onboardingType,
            'status' => TenantProvisioningRun::STATUS_DRAFT,
            'idempotency_key' => $data->idempotencyKey,
            'requested_by_user_id' => $actor?->id ?? $data->requestedByUserId,
            'billing_period' => null,
            'metadata_json' => [],
        ]);
    }

    private function validate(OnboardingRequestData $data, bool $requireIdempotency = true): void
    {
        if (! (bool) config('onboarding_governance.enabled', true)) {
            throw new OnboardingException('ONBOARDING_DISABLED', 'Onboarding is disabled by governance.');
        }

        // ONB-R018 — public/self-signup mutation is disabled by default.
        if (in_array($data->onboardingType, ['approved_signup'], true)
            && ! (bool) config('onboarding_governance.public_self_signup_mutation_enabled', false)) {
            throw new OnboardingException('PUBLIC_SIGNUP_DISABLED', 'Public self-signup mutation is disabled by governance.');
        }

        if ($requireIdempotency) {
            $key = $data->idempotencyKey;
            $min = (int) config('onboarding_governance.idempotency.key_min_length', 8);
            $max = (int) config('onboarding_governance.idempotency.key_max_length', 128);

            if (strlen($key) < $min || strlen($key) > $max) {
                throw new OnboardingException('IDEMPOTENCY_KEY_INVALID', 'A valid idempotency key is required for an onboarding mutation.');
            }
        }

        if ($data->planCode === '') {
            throw new OnboardingException('UNKNOWN_PLAN', 'A plan code is required.');
        }

        // ONB-R003 — fail closed on an unknown plan; never fall back to unlimited.
        $known = (array) config('tenant_plan.plan_keys', []);
        if (! in_array($data->planCode, $known, true)) {
            throw new OnboardingException('UNKNOWN_PLAN', "Plan '{$data->planCode}' is not a known plan; failing closed.");
        }

        if ($data->tenantName === '') {
            throw new OnboardingException('TENANT_NAME_REQUIRED', 'A tenant name is required.');
        }

        if ((bool) config('onboarding_governance.provisioning.first_branch_required', true)
            && $data->firstBranchName === '') {
            throw new OnboardingException('FIRST_BRANCH_REQUIRED', 'A first branch name is required.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function plannedSteps(OnboardingRequestData $data): array
    {
        return (array) config('onboarding_governance.steps', []);
    }
}
