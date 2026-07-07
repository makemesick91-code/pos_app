<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 11 — presents an admin audit log entry. before/after values are the
 * sanitized snapshots stored by AdminAuditLogger; they never contain secrets or
 * raw payment gateway payloads.
 *
 * @mixin AdminAuditLog
 */
class AdminAuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_user_id' => $this->actor_user_id,
            'actor' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
                'email' => $this->actor?->email,
            ]),
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'tenant_id' => $this->tenant_id,
            'before_values' => $this->before_values,
            'after_values' => $this->after_values,
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
