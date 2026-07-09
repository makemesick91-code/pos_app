<?php

namespace App\Console\Commands;

use App\Services\Observability\ObservabilityGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:governance-audit. Checks OBS-R001..OBS-R032 config /
 * guardrail / posture wiring. Returns non-zero on any FAIL signal. No secrets/PII.
 */
class ObservabilityGovernanceAuditCommand extends Command
{
    protected $signature = 'observability:governance-audit {--json : Output JSON}';

    protected $description = 'Audit observability governance (OBS-R001..OBS-R032) config/posture wiring.';

    public function handle(ObservabilityGovernanceAuditService $service): int
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
            if ($signal['status'] === ObservabilityGovernanceAuditService::STATUS_FAIL) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
