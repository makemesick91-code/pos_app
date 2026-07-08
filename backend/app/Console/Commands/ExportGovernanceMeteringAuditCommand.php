<?php

namespace App\Console\Commands;

use App\Services\ExportGovernance\ExportGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 29 — export-governance:metering-audit. Read-only enforcement audit for
 * export governance. Exit non-zero (NO_GO) on any critical gap: an unregistered
 * export-like route, a metered route missing the lifecycle/entitlement/usage
 * guard or ordering, a non-canonical meter/event key, a missing idempotency
 * strategy or sanitizer, an exemption without a reason, or a non-meterable meter
 * (EGC-R001..R013). Never prints secrets, never mutates.
 */
class ExportGovernanceMeteringAuditCommand extends Command
{
    protected $signature = 'export-governance:metering-audit {--json : Output JSON}';

    protected $description = 'Audit export governance enforcement; fail on unregistered/unguarded/non-idempotent export routes.';

    public function handle(ExportGovernanceAuditService $audit): int
    {
        $report = $audit->evaluate();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Export Governance Metering Audit');
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        return $report['decision'] === ExportGovernanceAuditService::DECISION_NO_GO
            ? self::FAILURE
            : self::SUCCESS;
    }
}
