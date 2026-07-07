<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a daily closing request (Sprint 9). Only the store, business date,
 * and notes are accepted from the client — tenant_id comes from context,
 * closed_by from the authenticated user, and ALL totals are computed by the
 * backend. Client-provided totals are ignored by design (never in the ruleset).
 * The business date may not be in the future.
 */
class StoreDailyClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantContext::class)->hasTenant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')->where('tenant_id', $tenantId),
            ],
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
