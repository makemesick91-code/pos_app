<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairPlanner;
use App\Services\UsageLedgerAnomaly\UsageLedgerRepairService;
use Illuminate\Console\Command;

/**
 * Sprint 28 — usage-ledger:repair-apply. Governed apply of auto-repairable
 * usage-ledger corrections (ULR-R007..R011).
 *
 * Safety: refuses to run without an explicit --apply or --dry-run; always requires
 * --reason and --actor; never mutates/deletes an append-only ledger event; only
 * appends governed correction records; is idempotent; clamps effective usage at
 * zero; and audit-logs each applied repair when a platform admin actor exists.
 */
class UsageLedgerRepairApplyCommand extends Command
{
    protected $signature = 'usage-ledger:repair-apply '
        .'{--apply : Explicitly apply the repairs (writes governed correction records)} '
        .'{--dry-run : Simulate without writing} '
        .'{--reason= : Required governed reason} '
        .'{--actor= : Required actor label (e.g. platform-admin/system)} '
        .'{--tenant= : Restrict to a tenant id} '
        .'{--meter= : Restrict to a meter key} '
        .'{--json : Output JSON}';

    protected $description = 'Governed apply (default refuses; needs --apply or --dry-run, plus --reason and --actor).';

    public function handle(UsageLedgerRepairPlanner $planner, UsageLedgerRepairService $service): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run');

        if (! $apply && ! $dryRun) {
            $this->error('Refusing to run: pass --dry-run to simulate or --apply to write governed repairs.');

            return self::FAILURE;
        }

        $reason = trim((string) ($this->option('reason') ?? ''));
        $actor = trim((string) ($this->option('actor') ?? ''));
        if ($reason === '') {
            $this->error('--reason is required.');

            return self::FAILURE;
        }
        if ($actor === '') {
            $this->error('--actor is required.');

            return self::FAILURE;
        }

        // --apply wins over --dry-run only when explicitly requested.
        $isDryRun = ! $apply;

        $tenant = $this->option('tenant');
        $meter = $this->option('meter');
        $decisions = $planner->plan(
            ($tenant === null || $tenant === '') ? null : (int) $tenant,
            ($meter === null || $meter === '') ? null : (string) $meter,
        );

        $auditActor = $isDryRun ? null : User::query()
            ->where('is_platform_admin', true)
            ->where('is_active', true)
            ->first();

        $result = $service->apply($decisions, $reason, $actor, $isDryRun, $auditActor);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $mode = $isDryRun ? 'DRY-RUN' : 'APPLY';
            $this->line("Usage Ledger Repair {$mode}");
            $this->line("applied={$result['applied_count']} skipped_manual_review={$result['skipped_manual_review']} already_applied={$result['already_applied']}");
            foreach ($result['applied'] as $a) {
                $this->line('  '.($a['dry_run'] ? '[would apply]' : '[applied]')
                    ." tenant={$a['tenant_id']} meter={$a['meter_key']} period={$a['period_key']} "
                    ."delta={$a['quantity_delta']} effective {$a['effective_before']}→{$a['effective_after']}");
            }
        }

        return self::SUCCESS;
    }
}
