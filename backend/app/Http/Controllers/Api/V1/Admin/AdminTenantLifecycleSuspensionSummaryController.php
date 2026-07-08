<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\TenantSuspensionSummaryResource;
use App\Services\TenantLifecycle\TenantSuspensionSummaryService;

/**
 * Sprint 25 — read-only manual suspension governance summary. Platform admin
 * only. Secret-safe counts by status/category and lifecycle event actions.
 */
class AdminTenantLifecycleSuspensionSummaryController extends Controller
{
    public function __construct(
        private readonly TenantSuspensionSummaryService $summary,
    ) {}

    public function show(): TenantSuspensionSummaryResource
    {
        return new TenantSuspensionSummaryResource($this->summary->summary());
    }
}
