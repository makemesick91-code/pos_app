<?php

namespace App\Console\Commands;

use App\Services\DataImport\ImportGovernanceAuditService;
use Illuminate\Console\Command;

class ImportGovernanceAuditCommand extends Command
{
    protected $signature = 'import:governance-audit {--json}';
    protected $description = 'Audit Sprint 37 import governance wiring.';

    public function handle(ImportGovernanceAuditService $governance): int
    {
        $signals = $governance->evaluate();
        if ($this->option('json')) {
            $this->line((string) json_encode(['signals' => $signals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($signals as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} - {$signal['message']}");
            }
        }

        return collect($signals)->contains(fn ($signal) => $signal['status'] === ImportGovernanceAuditService::STATUS_FAIL) ? self::FAILURE : self::SUCCESS;
    }
}
