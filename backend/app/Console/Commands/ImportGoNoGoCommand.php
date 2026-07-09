<?php

namespace App\Console\Commands;

use App\Services\DataImport\ImportGoNoGoService;
use Illuminate\Console\Command;

class ImportGoNoGoCommand extends Command
{
    protected $signature = 'import:go-no-go {--json} {--strict}';
    protected $description = 'Aggregate Sprint 37 import GO/WATCH/NO-GO checks.';

    public function handle(ImportGoNoGoService $service): int
    {
        $report = $service->evaluate();
        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($report['signals'] as $signal) {
                $this->line("[{$signal['status']}] {$signal['key']} - {$signal['message']}");
            }
            $this->line('Decision: '.$report['decision']);
        }

        if ($report['decision'] === ImportGoNoGoService::DECISION_NO_GO) {
            return self::FAILURE;
        }
        if ($this->option('strict') && $report['decision'] === ImportGoNoGoService::DECISION_WATCH) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
