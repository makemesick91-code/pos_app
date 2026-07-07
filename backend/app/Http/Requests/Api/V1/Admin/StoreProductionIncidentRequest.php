<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — validates creation of a production incident. tenant_id/store_id are
 * optional (some incidents are global); when a store is given it must belong to
 * the tenant (enforced by ProductionIncidentService). No secret is accepted as a
 * dedicated field; the service also sanitises free-text/metadata.
 */
class StoreProductionIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'area' => ['required', Rule::in(ProductionIncident::AREAS)],
            'severity' => ['required', Rule::in(ProductionIncident::SEVERITIES)],
            'status' => ['sometimes', Rule::in(ProductionIncident::STATUSES)],
            'impact' => ['required', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'detected_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
