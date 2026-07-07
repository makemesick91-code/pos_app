<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sprint 11 — filters for the admin audit log index. Platform admin only
 * (enforced by middleware). Supports actor/action/tenant/target/date filters.
 */
class IndexAdminAuditLogRequest extends FormRequest
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
            'actor_user_id' => ['sometimes', 'nullable', 'integer'],
            'action' => ['sometimes', 'nullable', 'string', 'max:64'],
            'target_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'tenant_id' => ['sometimes', 'nullable', 'integer'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
