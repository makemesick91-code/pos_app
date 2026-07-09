<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Observability\HealthCheckResource;
use App\Http\Resources\Api\Observability\InfrastructureHealthResource;
use App\Http\Resources\Api\Observability\ObservabilityMetricResource;
use App\Http\Resources\Api\Observability\QueueHealthResource;
use App\Http\Resources\Api\Observability\SchedulerHealthResource;
use App\Http\Resources\Api\Observability\TenantRuntimeProbeResource;
use App\Models\Tenant;
use App\Services\Observability\InfrastructureHealthCheckService;
use App\Services\Observability\ObservabilityGovernanceAuditService;
use App\Services\Observability\ObservabilityHealthService;
use App\Services\Observability\ObservabilityMetricsService;
use App\Services\Observability\QueueHealthService;
use App\Services\Observability\SchedulerHealthService;
use App\Services\Observability\TenantRuntimeProbeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 36 — the platform-admin, READ-ONLY observability overview surface
 * (OBS-R002/R003/R023). Every method reads through a governed service and returns
 * redacted, aggregate-safe data. No method mutates any state.
 */
class AdminObservabilityController extends Controller
{
    public function __construct(
        private readonly ObservabilityHealthService $health,
        private readonly InfrastructureHealthCheckService $infrastructure,
        private readonly QueueHealthService $queue,
        private readonly SchedulerHealthService $scheduler,
        private readonly TenantRuntimeProbeService $tenantProbe,
        private readonly ObservabilityMetricsService $metrics,
        private readonly ObservabilityGovernanceAuditService $governance,
    ) {}

    public function health(): JsonResponse
    {
        return response()->json(['data' => new HealthCheckResource($this->health->overview())]);
    }

    public function infrastructure(): JsonResponse
    {
        return response()->json(['data' => new InfrastructureHealthResource($this->infrastructure->check())]);
    }

    public function tenants(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->tenantProbe->probeMany((int) $request->input('limit', 25))]);
    }

    public function tenant(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => new TenantRuntimeProbeResource($this->tenantProbe->probe($tenant))]);
    }

    public function queues(): JsonResponse
    {
        return response()->json(['data' => new QueueHealthResource($this->queue->summary())]);
    }

    public function scheduler(): JsonResponse
    {
        return response()->json(['data' => new SchedulerHealthResource($this->scheduler->summary())]);
    }

    public function metrics(): JsonResponse
    {
        return response()->json(['data' => new ObservabilityMetricResource($this->metrics->summary())]);
    }

    public function governance(): JsonResponse
    {
        return response()->json(['data' => $this->governance->evaluate()]);
    }
}
