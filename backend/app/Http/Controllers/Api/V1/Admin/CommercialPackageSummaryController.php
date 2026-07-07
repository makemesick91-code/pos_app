<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\CommercialPackageSummaryResource;
use App\Services\Commercial\SaaSPackageCatalogService;

/**
 * Sprint 20 — read-only SaaS package summary. Platform admin only. Aggregate and
 * secret-safe; pricing is governance metadata only.
 */
class CommercialPackageSummaryController extends Controller
{
    public function __construct(private readonly SaaSPackageCatalogService $packages) {}

    public function index(): CommercialPackageSummaryResource
    {
        return new CommercialPackageSummaryResource($this->packages->summary());
    }
}
