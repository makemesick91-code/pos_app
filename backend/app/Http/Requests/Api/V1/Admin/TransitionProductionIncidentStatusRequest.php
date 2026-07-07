<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\ProductionIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 19 — transition a production incident's status (platform admin).
 * ACCEPTED_RISK must go through the dedicated accept-risk endpoint.
 */
class TransitionProductionIncidentStatusRequest extends FormRequest
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
            'status' => [
                'required',
                Rule::in(array_values(array_diff(ProductionIncident::STATUSES, [ProductionIncident::STATUS_ACCEPTED_RISK]))),
            ],
        ];
    }
}
