<?php

namespace App\Console\Commands;

use App\Http\Resources\TenantDataImportRowResource;
use App\Models\TenantDataImportRun;
use Illuminate\Console\Command;

class ImportRowsCommand extends Command
{
    protected $signature = 'import:rows {--run=}';
    protected $description = 'Show redacted import row results.';

    public function handle(): int
    {
        $run = TenantDataImportRun::findOrFail((int) $this->option('run'));
        $this->line((string) json_encode(TenantDataImportRowResource::collection($run->rows()->orderBy('row_number')->limit(200)->get())->resolve(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
