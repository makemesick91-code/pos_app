<?php

namespace App\Console\Commands;

use App\Services\SupportOperations\SupportGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:governance-audit. Checks the SUP rule/config/runtime
 * wiring (SUP-R001..R030). Returns non-zero on any FAIL signal. Never leaks
 * secrets/PII.
 */
class SupportOpsGovernanceAuditCommand extends Command
{
    protected $signature = 'support-ops:governance-audit {--json : Output JSON}';

    protected $description = 'Audit the Sprint 35 support-operations governance wiring (SUP-R001..R030).';

    public function handle(SupportGovernanceAuditService $service): int
    {
        $signals = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode(['signals' => $signals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Support Operations Governance Audit');
            foreach ($signals as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === SupportGovernanceAuditService::STATUS_FAIL) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
