<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\TenantUsageEvent;
use App\Services\UsageEventLedger\SanitizesUsageEventMetadata;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sprint 27 — platform-admin read-only view of a single usage event (UEL-R013).
 * Metadata is re-sanitized defensively on read so no secret can ever surface even
 * if a legacy row slipped one in (UEL-R003). Never exposes cross-tenant data by
 * itself — the controller always scopes the query to one tenant.
 *
 * @mixin TenantUsageEvent
 */
class UsageEventResource extends JsonResource
{
    use SanitizesUsageEventMetadata;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'event_key' => $this->event_key,
            'event_category' => $this->event_category,
            'meter_key' => $this->meter_key,
            'quantity' => (int) $this->quantity,
            'occurred_at' => optional($this->occurred_at)->toIso8601String(),
            'period_key' => $this->period_key,
            'source' => $this->source,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'metadata' => $this->sanitizeMetadata((array) ($this->metadata ?? [])),
        ];
    }
}
