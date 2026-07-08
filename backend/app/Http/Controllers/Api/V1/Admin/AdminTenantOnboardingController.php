<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StartTenantOnboardingRequest;
use App\Http\Resources\Api\V1\Admin\TenantProvisioningRunResource;
use App\Models\Tenant;
use App\Models\TenantProvisioningRun;
use App\Services\TenantOnboarding\OnboardingChecklistService;
use App\Services\TenantOnboarding\OnboardingException;
use App\Services\TenantOnboarding\OnboardingGoNoGoService;
use App\Services\TenantOnboarding\OnboardingGovernanceAuditService;
use App\Services\TenantOnboarding\OnboardingPlanReadinessService;
use App\Services\TenantOnboarding\OnboardingRequestData;
use App\Services\TenantOnboarding\TenantOnboardingService;
use App\Services\TenantOnboarding\TrialToPaidReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 33 — platform-admin onboarding surface (ONB-R018/R019/R024). All
 * mutations go through TenantOnboardingService (ONB-R001), require an idempotency
 * key, and are audited. There is deliberately NO public/tenant route that mutates
 * the onboarding lifecycle, and no route that marks an invoice paid or lifts a
 * suspension. Responses are redacted — no secrets or PII.
 */
class AdminTenantOnboardingController extends Controller
{
    public function __construct(
        private readonly TenantOnboardingService $onboarding,
        private readonly OnboardingChecklistService $checklist,
        private readonly TrialToPaidReadinessService $readiness,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $runs = TenantProvisioningRun::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('plan_code'), fn ($q, $v) => $q->where('requested_plan_code', $v))
            ->orderByDesc('id')
            ->paginate(25);

        return TenantProvisioningRunResource::collection($runs);
    }

    public function show(TenantProvisioningRun $run): TenantProvisioningRunResource
    {
        return new TenantProvisioningRunResource($run);
    }

    public function store(StartTenantOnboardingRequest $request): JsonResponse
    {
        $data = OnboardingRequestData::fromArray(array_merge($request->validated(), [
            'requested_by_user_id' => $request->user()?->id,
        ]));

        if ($request->boolean('dry_run')) {
            return response()->json(['data' => $this->onboarding->dryRun($data)]);
        }

        try {
            $run = $this->onboarding->execute($data, $request->user());
        } catch (OnboardingException $e) {
            return $this->denied($e);
        }

        return (new TenantProvisioningRunResource($run))
            ->response()
            ->setStatusCode(201);
    }

    public function retry(StartTenantOnboardingRequest $request, TenantProvisioningRun $run): JsonResponse
    {
        if ($request->input('idempotency_key') !== $run->idempotency_key) {
            return response()->json([
                'message' => 'The idempotency key must match the run being retried.',
                'code' => 'IDEMPOTENCY_KEY_MISMATCH',
            ], 422);
        }

        $data = OnboardingRequestData::fromArray(array_merge($request->validated(), [
            'requested_by_user_id' => $request->user()?->id,
        ]));

        try {
            $run = $this->onboarding->execute($data, $request->user());
        } catch (OnboardingException $e) {
            return $this->denied($e);
        }

        return (new TenantProvisioningRunResource($run))->response()->setStatusCode(200);
    }

    public function cancel(Request $request, TenantProvisioningRun $run): JsonResponse
    {
        try {
            $run = $this->onboarding->cancel($run, (string) $request->input('reason', ''));
        } catch (OnboardingException $e) {
            return $this->denied($e);
        }

        return (new TenantProvisioningRunResource($run))->response()->setStatusCode(200);
    }

    public function checklist(TenantProvisioningRun $run): JsonResponse
    {
        return response()->json(['data' => $this->checklist->build($run)]);
    }

    public function invoice(Request $request, TenantProvisioningRun $run): JsonResponse
    {
        $tenant = $this->tenantFor($run);

        if (! $tenant instanceof Tenant) {
            return response()->json(['message' => 'Run has no tenant yet.', 'code' => 'NO_TENANT'], 422);
        }

        $invoice = $this->readiness->generateInvoice($tenant, $request->user());
        $run->tenant_billing_invoice_id = $invoice->id;
        $run->billing_period = $invoice->period_key;
        $run->save();

        return response()->json(['data' => $this->readiness->summary($invoice)]);
    }

    public function paymentIntent(Request $request, TenantProvisioningRun $run): JsonResponse
    {
        if ($run->tenant_billing_invoice_id === null) {
            return response()->json(['message' => 'Generate an invoice first.', 'code' => 'NO_INVOICE'], 422);
        }

        $invoice = \App\Models\TenantBillingInvoice::query()->findOrFail($run->tenant_billing_invoice_id);
        $intent = $this->readiness->createPaymentIntent($invoice, $request->user(), $run->idempotency_key.':intent');
        $run->payment_intent_id = $intent->id;
        $run->save();

        return response()->json(['data' => [
            'payment_intent_id' => $intent->id,
            'status' => $intent->status,
        ]]);
    }

    public function governance(
        OnboardingGovernanceAuditService $governance,
        OnboardingGoNoGoService $goNoGo,
        OnboardingPlanReadinessService $plans,
    ): JsonResponse {
        return response()->json(['data' => [
            'rules' => config('onboarding_governance.rules'),
            'governance' => $governance->evaluate(),
            'go_no_go' => $goNoGo->evaluate(),
            'plan_readiness' => $plans->evaluate(),
        ]]);
    }

    private function tenantFor(TenantProvisioningRun $run): ?Tenant
    {
        return $run->tenant_id !== null ? Tenant::query()->find($run->tenant_id) : null;
    }

    private function denied(OnboardingException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code' => $e->reasonCode,
        ], 422);
    }
}
