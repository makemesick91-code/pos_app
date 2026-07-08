<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\UsageLedgerAnomaly\UsageLedgerAnomalySummary;
use Illuminate\Http\Request;

/**
 * Sprint 28 — platform-admin READ-ONLY usage-ledger anomaly visibility
 * (ULR-R012). Behind platform.admin. There is deliberately NO repair-apply route
 * and NO ledger mutation route here (ULR-R009); repair is CLI-only. All output is
 * redacted and never leaks secret values (ULR-R006).
 */
class AdminUsageLedgerAnomalyController extends Controller
{
    public function __construct(
        private readonly UsageLedgerAnomalySummary $summary,
    ) {}

    /**
     * Cross-tenant anomaly summary (platform admin only) — ULR-R012.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        return [
            'data' => $this->summary->summarize(
                null,
                $this->meter($request),
                $this->severity($request),
            ),
        ];
    }

    /**
     * Single-tenant anomaly summary (platform admin only) — scoped so it can never
     * leak another tenant's usage data (ULR-R012).
     *
     * @return array<string, mixed>
     */
    public function forTenant(Request $request, Tenant $tenant): array
    {
        return [
            'data' => $this->summary->summarize(
                (int) $tenant->id,
                $this->meter($request),
                $this->severity($request),
            ),
        ];
    }

    private function meter(Request $request): ?string
    {
        $v = $request->query('meter');

        return ($v === null || $v === '') ? null : (string) $v;
    }

    private function severity(Request $request): ?string
    {
        $v = $request->query('severity');

        return ($v === null || $v === '') ? null : (string) $v;
    }
}
