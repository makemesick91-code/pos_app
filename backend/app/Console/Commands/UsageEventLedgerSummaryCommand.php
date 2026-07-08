<?php

namespace App\Console\Commands;

use App\Services\UsageEventLedger\UsageEventLedgerService;
use Illuminate\Console\Command;

/**
 * Sprint 27 — usage-event-ledger:summary. Cross-tenant, per-meter usage event
 * ledger summary (counts only, redacted, no event payloads — UEL-R013). Never
 * prints secrets.
 */
class UsageEventLedgerSummaryCommand extends Command
{
    protected $signature = 'usage-event-ledger:summary {--json : Output JSON}';

    protected $description = 'Summarize the usage event ledger by meter/event/source (counts only).';

    public function handle(UsageEventLedgerService $ledger): int
    {
        $summary = $ledger->ledgerSummary();

        if ($this->option('json')) {
            $this->line((string) json_encode(['meters' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Usage Event Ledger Summary');
        if ($summary === []) {
            $this->line('  (no usage events recorded yet)');

            return self::SUCCESS;
        }
        foreach ($summary as $row) {
            $this->line(sprintf(
                '  %s / %s [%s] tenants=%d events=%d qty=%d',
                $row['meter_key'] ?? '(none)',
                $row['event_key'],
                $row['source'],
                $row['tenants'],
                $row['events'],
                $row['quantity'],
            ));
        }

        return self::SUCCESS;
    }
}
