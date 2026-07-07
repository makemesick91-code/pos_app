<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AcceptPilotDefectRiskRequest;
use App\Http\Requests\Api\V1\Admin\AssignPilotDefectRequest;
use App\Http\Requests\Api\V1\Admin\IndexPilotDefectRequest;
use App\Http\Requests\Api\V1\Admin\MarkPilotDefectFixedRequest;
use App\Http\Requests\Api\V1\Admin\StorePilotDefectRequest;
use App\Http\Requests\Api\V1\Admin\TransitionPilotDefectStatusRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePilotDefectRequest;
use App\Http\Requests\Api\V1\Admin\VerifyPilotDefectRequest;
use App\Http\Resources\Api\V1\Admin\PilotDefectResource;
use App\Models\AdminAuditLog;
use App\Models\PilotDefect;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Pilot\AcceptedRiskGovernanceService;
use App\Services\Pilot\FixVerificationService;
use App\Services\Pilot\PilotDefectService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 17 — platform-admin pilot defect register. Platform admin only
 * (platform.admin middleware); tenant business users are blocked. Every mutation
 * flows through the stabilization services (which append immutable events) and is
 * recorded to the admin audit log. No secrets are exposed; accepted risk never
 * hides the original severity.
 */
class PilotDefectController extends Controller
{
    public function __construct(
        private readonly PilotDefectService $defects,
        private readonly AcceptedRiskGovernanceService $acceptedRisk,
        private readonly FixVerificationService $verification,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function index(IndexPilotDefectRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = PilotDefect::query()->latest('id');
        foreach (['severity', 'status', 'area', 'tenant_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (array_key_exists('blocking', $filters)) {
            $query->where('blocking', (bool) $filters['blocking']);
        }

        return PilotDefectResource::collection(
            $query->paginate((int) ($filters['per_page'] ?? 20)),
        );
    }

    public function store(StorePilotDefectRequest $request): PilotDefectResource
    {
        $defect = $this->defects->create($request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_CREATED,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            after: ['severity' => $defect->severity, 'status' => $defect->status, 'blocking' => $defect->blocking],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }

    public function show(PilotDefect $defect): PilotDefectResource
    {
        return new PilotDefectResource($defect->load('events'));
    }

    public function update(UpdatePilotDefectRequest $request, PilotDefect $defect): PilotDefectResource
    {
        $before = ['severity' => $defect->severity, 'status' => $defect->status];
        $defect = $this->defects->update($defect, $request->validated(), $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_UPDATED,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            before: $before,
            after: ['severity' => $defect->severity, 'status' => $defect->status],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }

    public function assign(AssignPilotDefectRequest $request, PilotDefect $defect): PilotDefectResource
    {
        $defect = $this->defects->assign($defect, $request->validated()['assigned_to'], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_ASSIGNED,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            after: ['assigned_to' => $defect->assigned_to],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }

    public function status(TransitionPilotDefectStatusRequest $request, PilotDefect $defect): PilotDefectResource
    {
        $data = $request->validated();
        $before = $defect->status;
        $defect = $this->defects->transitionStatus($defect, $data['status'], $request->user());

        if (! empty($data['message'])) {
            $this->defects->comment($defect, $data['message'], $request->user());
        }

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_STATUS_CHANGED,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            before: ['status' => $before],
            after: ['status' => $defect->status],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }

    public function acceptRisk(AcceptPilotDefectRiskRequest $request, PilotDefect $defect): PilotDefectResource
    {
        $data = $request->validated();
        $defect = $this->acceptedRisk->accept($defect, [
            'reason' => $data['reason'],
            'approver' => $data['approver_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'evidence_reference' => $data['evidence_reference'] ?? null,
        ], $request->user());

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_ACCEPTED_RISK,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            after: ['status' => $defect->status, 'preserved_severity' => $defect->severity],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }

    public function markFixed(MarkPilotDefectFixedRequest $request, PilotDefect $defect): PilotDefectResource
    {
        $evidence = $request->validated()['evidence_reference'] ?? null;
        $defect = $this->verification->markFixed($defect, $request->user(), $evidence);

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_FIXED,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            after: ['status' => $defect->status],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }

    public function verify(VerifyPilotDefectRequest $request, PilotDefect $defect): PilotDefectResource
    {
        $data = $request->validated();
        $defect = $this->verification->verify(
            $defect,
            (bool) $data['passed'],
            $request->user(),
            $data['evidence_reference'] ?? null,
            (bool) ($data['close'] ?? false),
        );

        $this->audit->log(
            actor: $request->user(),
            action: AdminAuditLog::ACTION_DEFECT_VERIFIED,
            targetType: AdminAuditLog::TARGET_PILOT_DEFECT,
            targetId: $defect->id,
            tenantId: $defect->tenant_id,
            after: ['status' => $defect->status, 'verification_result' => $defect->verification_result],
            request: $request,
        );

        return new PilotDefectResource($defect->load('events'));
    }
}
