<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 35 — a redacted, tenant-isolated support incident note (SUP-R023).
 */
class TenantSupportIncidentNote extends Model
{
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_TENANT_VISIBLE = 'tenant_visible';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'tenant_support_incident_id',
        'tenant_id',
        'author_user_id',
        'note_type',
        'body_safe',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(TenantSupportIncident::class, 'tenant_support_incident_id');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->tenant_support_incident_id,
            'tenant_id' => $this->tenant_id,
            'note_type' => $this->note_type,
            'body' => $this->body_safe,
            'author_user_id' => $this->author_user_id,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
