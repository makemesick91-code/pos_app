<?php

namespace App\Console\Commands;

use App\Services\DataImport\ImportTemplateService;
use Illuminate\Console\Command;

class ImportTemplateCommand extends Command
{
    protected $signature = 'import:template {--type= : Import type} {--csv : Print CSV header}';
    protected $description = 'Show Sprint 37 import template metadata or CSV header.';

    public function handle(ImportTemplateService $templates): int
    {
        $type = $this->option('type');
        if ($type && $this->option('csv')) {
            $this->line($templates->csv((string) $type));
            return self::SUCCESS;
        }

        $this->line((string) json_encode($type ? $templates->metadata((string) $type) : $templates->list(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
