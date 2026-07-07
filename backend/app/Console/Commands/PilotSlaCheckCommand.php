<?php

namespace App\Console\Commands;

use App\Services\Pilot\SlaBreachDetectionService;
use Illuminate\Console\Command;

/**
 * Sprint 17 — pilot:sla-check.
 *
 * Detects open defects past their severity SLA. READ-ONLY by default (CI uses
 * this). Only --mark-breached persists sla_breached_at and appends an
 * SLA_BREACHED event. Never sends real alerts, never prints secrets. Exit code:
 * 0 — no breach / breaches only (unless --strict), 1 — strict with breaches.
 */
class PilotSlaCheckCommand extends Command
{
    protected $signature = 'pilot:sla-check
        {--json : Output JSON}
        {--strict : Fail on warnings}
        {--mark-breached : Persist sla_breached_at and append event for overdue defects}';

    protected $description = 'Detect SLA-breached pilot defects (read-only unless --mark-breached).';

    public function handle(SlaBreachDetectionService $service): int
    {
        $marked = 0;
        if ($this->option('mark-breached')) {
            $marked = $service->markBreaches();
        }

        $summary = $service->summary();
        $report = [
            'breached_count' => $summary['breached_count'],
            'by_severity' => $summary['by_severity'],
            'marked' => $marked,
            'mode' => $this->option('mark-breached') ? 'mark' : 'read-only',
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Pilot SLA Check ('.$report['mode'].')');
            $this->line('SLA breached (open): '.$summary['breached_count']);
            if ($this->option('mark-breached')) {
                $this->line('Newly flagged: '.$marked);
            }
        }

        if ($this->option('strict') && $summary['breached_count'] > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
