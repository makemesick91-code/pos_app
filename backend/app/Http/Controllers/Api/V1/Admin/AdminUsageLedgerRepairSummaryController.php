<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairSummaryService;
use Illuminate\Http\Request;

/**
 * Sprint 28 — platform-admin READ-ONLY governed repair history (ULR-R008,
 * ULR-R012). Behind platform.admin. Read-only: there is no apply route here;
 * repair is CLI-only (ULR-R009). Output is redacted.
 */
class AdminUsageLedgerRepairSummaryController extends Controller
{
    public function __construct(
        private readonly UsageLedgerRepairSummaryService $summary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request): array
    {
        $tenant = $request->query('tenant');
        $meter = $request->query('meter');

        return [
            'data' => $this->summary->summary(
                ($tenant === null || $tenant === '') ? null : (int) $tenant,
                ($meter === null || $meter === '') ? null : (string) $meter,
            ),
        ];
    }
}
