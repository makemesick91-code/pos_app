<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPerformanceRunRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['profile' => ['required', 'string', Rule::in(array_keys((array) config('performance_governance.profiles', [])))], 'reason_code' => ['required', 'string', 'max:80']];
    }
}
