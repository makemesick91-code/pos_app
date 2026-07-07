<?php

namespace App\Http\Requests\Api\V1;

use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancel a sale. No client-supplied body is trusted; cancelled_by is always the
 * authenticated user.
 */
class CancelSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantContext::class)->hasTenant();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
