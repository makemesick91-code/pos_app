<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * Sprint 1 diagnostic endpoint proving that tenant/store context is resolved
 * from the authenticated user (and validated X-Store-ID), not client input.
 */
class TenantContextController extends Controller
{
    public function show(TenantContext $context): JsonResponse
    {
        return response()->json($context->toArray());
    }
}
