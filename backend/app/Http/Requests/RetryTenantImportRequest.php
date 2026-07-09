<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetryTenantImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', 'string', 'max:80'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
