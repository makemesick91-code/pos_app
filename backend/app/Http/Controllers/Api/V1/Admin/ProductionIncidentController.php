<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptProductionIncidentRiskRequest;
use App\Http\Requests\Api\V1\Admin\AssignProductionIncidentRequest;
use App\Http\Requests\Api\V1\Admin\IndexProductionIncidentRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductionIncidentRequest;
use App\Http\Requests\Api\V1\Admin\TransitionProductionIncidentStatusRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductionIncidentRequest;
use App\Http\Resources\Api\V1\Admin\ProductionIncidentResource;
use App\Models\AdminAuditLog;
use App\Models\ProductionIncident;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Operations\ProductionIncidentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 19 — platform-admin production incident register. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. Every mutation
 * flows through the incident service and is recorded to the admin audit log. No
 * secrets are exposed; accepted risk never hides the original severity.
 */
class ProductionIncidentController extends Controller
{
    public function __construct(
        private readonly ProductionIncidentService $incidents,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexProductionIncidentRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = ProductionIncident::query()->latest('id');
        foreach (['severity', 'status', 'area', 'tenant_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return ProductionIncidentResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StoreProductionIncidentRequest $request): ProductionIncidentResource
    {
        $incident = $this->incidents->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_INCIDENT_CREATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            targetId: $incident->id,
            tenantId: $incident->tenant_id,
            after: ['severity' => $incident->severity, 'status' => $incident->status, 'area' => $incident->area],
            request: $request,
        );

        return new ProductionIncidentResource($incident);
    }

    public function show(ProductionIncident $incident): ProductionIncidentResource
    {
        return new ProductionIncidentResource($incident);
    }

    public function update(UpdateProductionIncidentRequest $request, ProductionIncident $incident): ProductionIncidentResource
    {
        $before = ['severity' => $incident->severity, 'status' => $incident->status];
        $incident = $this->incidents->update($incident, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_INCIDENT_UPDATED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            targetId: $incident->id,
            tenantId: $incident->tenant_id,
            before: $before,
            after: ['severity' => $incident->severity, 'status' => $incident->status],
            request: $request,
        );

        return new ProductionIncidentResource($incident);
    }

    public function assign(AssignProductionIncidentRequest $request, ProductionIncident $incident): ProductionIncidentResource
    {
        $incident = $this->incidents->assign($incident, $request->validated()['assigned_to'], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_INCIDENT_ASSIGNED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            targetId: $incident->id,
            tenantId: $incident->tenant_id,
            after: ['assigned_to' => $incident->assigned_to],
            request: $request,
        );

        return new ProductionIncidentResource($incident);
    }

    public function status(TransitionProductionIncidentStatusRequest $request, ProductionIncident $incident): ProductionIncidentResource
    {
        $before = $incident->status;
        $incident = $this->incidents->transitionStatus($incident, $request->validated()['status'], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_INCIDENT_STATUS_CHANGED,
            targetType: AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            targetId: $incident->id,
            tenantId: $incident->tenant_id,
            before: ['status' => $before],
            after: ['status' => $incident->status],
            request: $request,
        );

        return new ProductionIncidentResource($incident);
    }

    public function acceptRisk(AcceptProductionIncidentRiskRequest $request, ProductionIncident $incident): ProductionIncidentResource
    {
        $data = $request->validated();
        $incident = $this->incidents->acceptRisk($incident, [
            'reason' => $data['reason'],
            'approver' => $data['approver_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'evidence_reference' => $data['evidence_reference'] ?? null,
        ], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_INCIDENT_ACCEPTED_RISK,
            targetType: AdminAuditLog::TARGET_PRODUCTION_INCIDENT,
            targetId: $incident->id,
            tenantId: $incident->tenant_id,
            after: ['status' => $incident->status, 'preserved_severity' => $incident->severity],
            request: $request,
        );

        return new ProductionIncidentResource($incident);
    }
}
