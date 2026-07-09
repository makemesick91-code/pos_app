<?php

namespace App\Http\Controllers;

use App\Services\Observability\ObservabilityHealthService;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 36 — public, minimal liveness/readiness endpoints (OBS-R001).
 *
 * Liveness returns only { status: ok, timestamp }. Readiness returns only
 * { status: ok|degraded, timestamp }. NEITHER exposes any tenant data,
 * environment secret, DB credential, or PII. These are safe to sit behind a
 * public load balancer. Disable per-env via config if a fronting LB owns them.
 */
class HealthCheckController extends Controller
{
    public function live(): JsonResponse
    {
        if (! (bool) config('observability_governance.public_liveness_enabled', true)) {
            return response()->json(['status' => 'disabled'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function ready(ObservabilityHealthService $health): JsonResponse
    {
        if (! (bool) config('observability_governance.public_readiness_enabled', true)) {
            return response()->json(['status' => 'disabled'], 404);
        }

        $readiness = $health->readiness();
        $code = $readiness['status'] === 'ok' ? 200 : 503;

        // Only status + timestamp — never component internals or tenant data.
        return response()->json([
            'status' => $readiness['status'],
            'timestamp' => $readiness['checked_at'],
        ], $code);
    }
}
