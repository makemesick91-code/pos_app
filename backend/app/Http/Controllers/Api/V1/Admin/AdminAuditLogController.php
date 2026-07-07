<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\IndexAdminAuditLogRequest;
use App\Http\Resources\Api\V1\Admin\AdminAuditLogResource;
use App\Models\AdminAuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — admin audit log read API. Platform admin only. Supports filtering
 * by actor/action/target/tenant/date. Snapshots were sanitized on write, so no
 * secrets or raw gateway payloads are ever returned.
 */
class AdminAuditLogController extends Controller
{
    public function index(IndexAdminAuditLogRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $limit = max(1, min((int) ($filters['limit'] ?? 50), 100));

        $query = AdminAuditLog::query()->with('actor')->orderByDesc('id');

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', (int) $filters['actor_user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', (string) $filters['action']);
        }

        if (! empty($filters['target_type'])) {
            $query->where('target_type', (string) $filters['target_type']);
        }

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return AdminAuditLogResource::collection($query->paginate($limit))->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }

    public function show(AdminAuditLog $auditLog): JsonResource
    {
        return AdminAuditLogResource::make($auditLog->load('actor'))->additional([
            'meta' => ['foundation' => 'POS_ANDROID_SAAS_FOUNDATION'],
        ]);
    }
}
