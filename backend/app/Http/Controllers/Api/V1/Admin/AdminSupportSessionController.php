<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportOps\StartSupportImpersonationRequest;
use App\Http\Requests\Api\SupportOps\StartSupportReadOnlyContextRequest;
use App\Http\Resources\Api\SupportOps\SupportSessionResource;
use App\Models\Tenant;
use App\Models\TenantSupportSession;
use App\Services\SupportOperations\SupportException;
use App\Services\SupportOperations\SupportImpersonationService;
use App\Services\SupportOperations\SupportReadOnlyContextService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 35 — platform-admin support session surface (SUP-R017/R018/R019).
 *
 * Starts a time-bound read-only context; impersonation is disabled by default
 * and returns a governed disabled response (never a credential/token).
 */
class AdminSupportSessionController extends Controller
{
    public function __construct(
        private readonly SupportReadOnlyContextService $readOnly,
        private readonly SupportImpersonationService $impersonation,
    ) {}

    public function startReadOnlyContext(StartSupportReadOnlyContextRequest $request, Tenant $tenant): JsonResponse
    {
        $session = $this->readOnly->start(
            $tenant,
            $request->user(),
            $request->input('reason_code'),
            $request->filled('ttl_minutes') ? (int) $request->input('ttl_minutes') : null,
        );

        return (new SupportSessionResource($session))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function end(TenantSupportSession $session): JsonResponse
    {
        $session = $this->readOnly->end($session, request()->user());

        return (new SupportSessionResource($session))->response();
    }

    public function startImpersonation(StartSupportImpersonationRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            $this->impersonation->start($tenant, $request->user(), $request->input('reason_code'));
        } catch (SupportException $e) {
            return response()->json([
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
                'meta' => ['impersonation_enabled' => $this->impersonation->isEnabled(), 'started' => false],
            ], $e->httpStatus);
        }

        // Unreachable while impersonation is disabled.
        return response()->json(['meta' => ['started' => true]]);
    }
}
