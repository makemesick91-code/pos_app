<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateTenantImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'branch_id' => ['nullable', 'integer', 'exists:stores,id'],
            'type' => ['required', 'string'],
            'file' => ['required', 'file'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
