<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 11 — validates an admin subscription update. Only status/date fields
 * may change; the plan and tenant binding are immutable here. Date consistency
 * is enforced. Audit before/after is captured in the service.
 */
class UpdateAdminTenantSubscriptionRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(StoreAdminTenantSubscriptionRequest::statuses())],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'grace_ends_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            if ($startsAt !== null && $endsAt !== null && strtotime((string) $endsAt) < strtotime((string) $startsAt)) {
                $validator->errors()->add('ends_at', 'The ends_at must be on or after starts_at.');
            }
        });
    }
}
