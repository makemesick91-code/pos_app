<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\LeadInterestSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Sprint 21 — validates a lead interest submission status change. A lead status
 * change is a review action only; it never provisions a tenant/user/subscription/
 * device.
 */
class TransitionLeadInterestSubmissionRequest extends FormRequest
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
            'status' => ['required', Rule::in(LeadInterestSubmission::STATUSES)],
        ];
    }
}
