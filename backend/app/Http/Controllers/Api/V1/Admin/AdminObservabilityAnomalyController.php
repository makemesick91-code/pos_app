<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Observability\AcknowledgeAnomalyRequest;
use App\Http\Requests\Api\Observability\ResolveAnomalyRequest;
use App\Http\Resources\Api\Observability\ObservabilityAnomalyResource;
use App\Models\ObservabilityAnomalyEvent;
use App\Services\Observability\ObservabilityAnomalyScanService;
use App\Services\Observability\ObservabilityException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 36 — platform-admin anomaly explorer + governed acknowledge/resolve
 * (OBS-R003/R028). Acknowledge/resolve mutate ONLY the observability anomaly
 * status (never a domain state); each requires a reason code and is audited.
 */
class AdminObservabilityAnomalyController extends Controller
{
    public function __construct(private readonly ObservabilityAnomalyScanService $scan) {}

    public function index(Request $request): JsonResponse
    {
        $query = ObservabilityAnomalyEvent::query()->orderByDesc('id');
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', (string) $request->input('category'));
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', (int) $request->input('tenant_id'));
        }
        $limit = max(1, min((int) $request->input('limit', 50), 200));

        return response()->json([
            'data' => ObservabilityAnomalyResource::collection($query->limit($limit)->get()),
        ]);
    }

    public function show(ObservabilityAnomalyEvent $anomaly): JsonResponse
    {
        return response()->json(['data' => new ObservabilityAnomalyResource($anomaly)]);
    }

    public function acknowledge(AcknowledgeAnomalyRequest $request, ObservabilityAnomalyEvent $anomaly): JsonResponse
    {
        try {
            $anomaly = $this->scan->acknowledge($anomaly, $request->user(), $request->input('reason_code'));
        } catch (ObservabilityException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => new ObservabilityAnomalyResource($anomaly)]);
    }

    public function resolve(ResolveAnomalyRequest $request, ObservabilityAnomalyEvent $anomaly): JsonResponse
    {
        try {
            $anomaly = $this->scan->resolve($anomaly, $request->user(), $request->input('reason_code'), (bool) $request->boolean('ignore'));
        } catch (ObservabilityException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => new ObservabilityAnomalyResource($anomaly)]);
    }
}
