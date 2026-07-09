<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Observability\AcceptAlertSuggestionRequest;
use App\Http\Requests\Api\Observability\DismissAlertSuggestionRequest;
use App\Http\Resources\Api\Observability\AlertSuggestionResource;
use App\Models\ObservabilityAlertSuggestion;
use App\Services\Observability\ObservabilityException;
use App\Services\Observability\ObservabilityIncidentSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sprint 36 — platform-admin alert / incident suggestion surface (OBS-R018/R028).
 * Accepting a suggestion may create a Sprint 35 support incident through the
 * governed SupportIncidentService (audited); it never mutates any other tenant
 * state. Dismiss is audited.
 */
class AdminObservabilityAlertController extends Controller
{
    public function __construct(private readonly ObservabilityIncidentSuggestionService $suggestions) {}

    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->input('status', ObservabilityAlertSuggestion::STATUS_SUGGESTED);

        return response()->json(['data' => $this->suggestions->list($status, (int) $request->input('limit', 100))]);
    }

    public function dismiss(DismissAlertSuggestionRequest $request, ObservabilityAlertSuggestion $suggestion): JsonResponse
    {
        try {
            $suggestion = $this->suggestions->dismiss($suggestion, $request->user(), $request->input('reason_code'));
        } catch (ObservabilityException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => new AlertSuggestionResource($suggestion)]);
    }

    public function accept(AcceptAlertSuggestionRequest $request, ObservabilityAlertSuggestion $suggestion): JsonResponse
    {
        try {
            $suggestion = $this->suggestions->accept($suggestion, $request->user(), $request->input('reason_code'));
        } catch (ObservabilityException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['data' => new AlertSuggestionResource($suggestion)]);
    }
}
