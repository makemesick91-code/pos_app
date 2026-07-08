<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SupportOps\StoreSupportIncidentNoteRequest;
use App\Http\Requests\Api\SupportOps\StoreSupportIncidentRequest;
use App\Http\Requests\Api\SupportOps\UpdateSupportIncidentRequest;
use App\Http\Resources\Api\SupportOps\SupportIncidentNoteResource;
use App\Http\Resources\Api\SupportOps\SupportIncidentResource;
use App\Models\Tenant;
use App\Models\TenantSupportIncident;
use App\Services\SupportOperations\SupportIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 35 — platform-admin support incident management (SUP-R023/R024). Every
 * mutation requires a reason code and is audited; notes/titles/summaries are
 * redacted. Incidents are tenant-isolated.
 */
class AdminSupportIncidentController extends Controller
{
    public function __construct(private readonly SupportIncidentService $incidents) {}

    public function index(Request $request): JsonResponse
    {
        $query = TenantSupportIncident::query()->orderByDesc('id');
        if ($request->filled('tenant_id')) {
            $query->forTenant((int) $request->input('tenant_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', (string) $request->input('severity'));
        }
        $limit = max(1, min((int) $request->input('limit', 50), 100));

        return response()->json([
            'data' => SupportIncidentResource::collection($query->limit($limit)->get()),
        ]);
    }

    public function store(StoreSupportIncidentRequest $request): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail((int) $request->input('tenant_id'));

        $incident = $this->incidents->create($tenant, $request->user(), $request->validated());

        return (new SupportIncidentResource($incident))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TenantSupportIncident $incident): JsonResponse
    {
        $incident->load('notes');

        return response()->json([
            'data' => array_merge($incident->toSafeArray(), [
                'notes' => $incident->notes->map->toSafeArray()->all(),
            ]),
        ]);
    }

    public function update(UpdateSupportIncidentRequest $request, TenantSupportIncident $incident): JsonResponse
    {
        $incident = $this->incidents->update($incident, $request->user(), $request->validated());

        return (new SupportIncidentResource($incident))->response();
    }

    public function addNote(StoreSupportIncidentNoteRequest $request, TenantSupportIncident $incident): JsonResponse
    {
        $note = $this->incidents->addNote($incident, $request->user(), $request->validated());

        return (new SupportIncidentNoteResource($note))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
