<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Observability\RetryFailedJobRequest;
use App\Http\Resources\Api\Observability\FailedJobDiagnosticResource;
use App\Services\Observability\FailedJobDiagnosticsService;
use App\Services\Observability\ObservabilityException;
use App\Services\Observability\QueueActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 36 — platform-admin failed-job diagnostics + governed retry
 * (OBS-R009/R010). Diagnostics are redacted (no payload/exception). Retry is
 * disabled by default and returns a governed "not supported" response.
 */
class AdminObservabilityFailedJobController extends Controller
{
    public function __construct(
        private readonly FailedJobDiagnosticsService $diagnostics,
        private readonly QueueActionService $queueAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new FailedJobDiagnosticResource($this->diagnostics->summary((int) $request->input('limit', 100))),
        ]);
    }

    public function retry(RetryFailedJobRequest $request, string $job): JsonResponse
    {
        try {
            $result = $this->queueAction->retry($request->user(), $job, $request->input('reason_code'));
        } catch (ObservabilityException $e) {
            return response()->json(['message' => $e->getMessage(), 'retry_enabled' => $this->queueAction->retryEnabled()], $e->status);
        }

        return response()->json(['data' => $result]);
    }
}
