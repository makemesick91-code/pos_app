<?php

namespace App\Console\Commands;

use App\Services\SupportOperations\SupportAndroidRuntimeViewerService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:sync-failures. Sync failure/conflict inspection sourced
 * from the Sprint 34 sync ledgers (SUP-R022). No raw sync payload. No PII/secrets.
 */
class SupportOpsSyncFailuresCommand extends Command
{
    protected $signature = 'support-ops:sync-failures {--tenant= : Tenant id or code} {--limit=100} {--json}';

    protected $description = 'Show Sprint 34 sync failures/conflicts for a tenant (redacted).';

    public function handle(SupportAndroidRuntimeViewerService $runtime): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));
        if ($tenant === null) {
            $this->line('No tenant specified (or not found); pass --tenant=<id|code>.');

            return self::SUCCESS;
        }

        $data = $runtime->syncFailures($tenant->id, (int) $this->option('limit'));
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Sync failures for tenant #'.$tenant->id);
        $this->line('  failed_batches: '.$data['failed_batch_count'].' failed_items: '.$data['failed_item_count']);

        return self::SUCCESS;
    }
}
