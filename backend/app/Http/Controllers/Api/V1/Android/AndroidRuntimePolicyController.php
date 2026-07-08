<?php

namespace App\Http\Controllers\Api\V1\Android;

use App\Http\Controllers\Controller;
use App\Services\AndroidRuntime\AndroidOfflinePolicyService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 34 — exposes the safe Android offline/runtime policy (ADR-R019/R025).
 * Read-only; carries no secrets or PII.
 */
class AndroidRuntimePolicyController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AndroidOfflinePolicyService $policy,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->policy->policyFor($this->context->tenant(), $this->context->user()),
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }
}
