<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\UsageEventLedger\ReportExportMeteringSummaryService;

/**
 * Sprint 27 — platform-admin read-only report export metering summary: the
 * current-month `reports.exports.monthly` consumption across tenants, derived
 * from the append-only ledger (UEL-R006, UEL-R013). Read-only; never mutates.
 */
class AdminReportExportMeteringSummaryController extends Controller
{
    public function __construct(
        private readonly ReportExportMeteringSummaryService $summary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        return [
            'data' => $this->summary->summary(),
        ];
    }
}
