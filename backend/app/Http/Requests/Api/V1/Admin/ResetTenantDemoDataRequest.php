<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 12 — validates a guarded demo-data reset. The reset only proceeds when
 * confirm_demo_reset is explicitly true; the destructive path is never the
 * default. dry_run returns a would-delete summary without deleting anything.
 */
class ResetTenantDemoDataRequest extends FormRequest
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
            'confirm_demo_reset' => ['required', 'accepted'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }
}
