<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\BillingInvoiceSummaryResource;
use App\Services\BillingCollection\BillingInvoiceService;

/**
 * Sprint 23 — read-only billing invoice summary. Platform admin only. Totals are
 * server-computed. No secrets are exposed.
 */
class BillingInvoiceSummaryController extends Controller
{
    public function __construct(
        private readonly BillingInvoiceService $invoices,
    ) {}

    public function index(): BillingInvoiceSummaryResource
    {
        return new BillingInvoiceSummaryResource($this->invoices->summary());
    }
}
