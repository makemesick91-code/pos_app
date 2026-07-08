<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 24 — validates the explicit manual apply of a renewal decision. The
 * service enforces the RECORDED + APPROVE_MANUAL_RENEWAL/APPROVE_WITH_RISK + decider
 * + effective-dates guardrails; apply is never triggered automatically.
 */
class ApplyManualRenewalDecisionRequest extends FormRequest
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
            'confirm' => ['nullable', 'boolean'],
        ];
    }
}
