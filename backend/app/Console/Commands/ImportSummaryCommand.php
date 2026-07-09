<?php

namespace App\Console\Commands;

use App\Http\Resources\TenantDataImportRunResource;
use App\Models\TenantDataImportRun;
use Illuminate\Console\Command;

class ImportSummaryCommand extends Command
{
    protected $signature = 'import:summary {--tenant=} {--status=} {--type=}';
    protected $description = 'Show redacted import run summaries.';

    public function handle(): int
    {
        $query = TenantDataImportRun::query()->latest();
        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }
        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }
        if ($this->option('type')) {
            $query->where('import_type', $this->option('type'));
        }
        $this->line((string) json_encode(TenantDataImportRunResource::collection($query->limit(25)->get())->resolve(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
