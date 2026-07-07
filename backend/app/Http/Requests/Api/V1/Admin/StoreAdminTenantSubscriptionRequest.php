<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\TenantSubscription;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 11 — validates an admin subscription assignment. The plan must exist;
 * the status must be a known subscription status; date consistency is enforced.
 * No billing/charge is performed — this only sets the subscription foundation.
 */
class StoreAdminTenantSubscriptionRequest extends FormRequest
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
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'status' => ['required', Rule::in(self::statuses())],
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

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            TenantSubscription::STATUS_TRIAL,
            TenantSubscription::STATUS_ACTIVE,
            TenantSubscription::STATUS_GRACE,
            TenantSubscription::STATUS_EXPIRED,
            TenantSubscription::STATUS_CANCELLED,
            TenantSubscription::STATUS_SUSPENDED,
        ];
    }
}
