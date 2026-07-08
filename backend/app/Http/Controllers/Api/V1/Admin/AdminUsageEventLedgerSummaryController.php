<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\UsageEventLedger\UsageEventLedgerService;

/**
 * Sprint 27 — platform-admin read-only, cross-tenant usage event ledger summary
 * (counts only, no event payloads, no per-tenant PII) — UEL-R013. Read-only:
 * there is no runtime mutation of the append-only ledger (UEL-R002).
 */
class AdminUsageEventLedgerSummaryController extends Controller
{
    public function __construct(
        private readonly UsageEventLedgerService $ledger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        return [
            'data' => [
                'meters' => $this->ledger->ledgerSummary(),
            ],
        ];
    }
}
