<?php

namespace App\Console\Commands;

use App\Services\AndroidRuntime\AndroidRuntimeGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 34 — android-runtime:governance-audit. Verifies the ADR rules/config/
 * guardrail wiring. Returns non-zero on any FAIL signal.
 */
class AndroidRuntimeGovernanceAuditCommand extends Command
{
    protected $signature = 'android-runtime:governance-audit {--json : Output JSON}';

    protected $description = 'Audit Android runtime governance (ADR-R001..R030) config/guardrail wiring.';

    public function handle(AndroidRuntimeGovernanceAuditService $service): int
    {
        $signals = $service->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode(['signals' => $signals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($signals as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === AndroidRuntimeGovernanceAuditService::STATUS_FAIL) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
