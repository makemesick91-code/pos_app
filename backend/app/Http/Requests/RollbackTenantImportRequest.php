<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RollbackTenantImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required_if:execute,true', 'nullable', 'string', 'max:80'],
            'execute' => ['boolean'],
        ];
    }
}
