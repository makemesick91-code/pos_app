<?php

namespace App\Console\Commands;

use App\Http\Resources\TenantDataImportRunResource;
use App\Models\Tenant;
use App\Services\DataImport\TenantDataImportService;
use Illuminate\Console\Command;

class ImportValidateCommand extends Command
{
    protected $signature = 'import:validate {--tenant=} {--type=} {--file=} {--branch=} {--idempotency-key=}';
    protected $description = 'Dry-run validate a tenant CSV import.';

    public function handle(TenantDataImportService $imports): int
    {
        $run = $imports->validateFile(
            Tenant::findOrFail((int) $this->option('tenant')),
            (string) $this->option('type'),
            (string) $this->option('file'),
            null,
            $this->option('branch') ? (int) $this->option('branch') : null,
            $this->option('idempotency-key') ?: null,
        );

        $this->line((string) json_encode((new TenantDataImportRunResource($run))->resolve(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
