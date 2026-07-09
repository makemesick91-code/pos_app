<?php

namespace App\Console\Commands;

use App\Http\Resources\TenantDataImportRunResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DataImport\TenantDataImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportExecuteCommand extends Command
{
    protected $signature = 'import:execute {--tenant=} {--type=} {--file=} {--branch=} {--idempotency-key=} {--reason=} {--execute} {--actor=}';
    protected $description = 'Execute a tenant CSV import; without --execute it remains dry-run.';

    public function handle(TenantDataImportService $imports): int
    {
        $tenant = Tenant::findOrFail((int) $this->option('tenant'));
        if (! $this->option('execute')) {
            $run = $imports->validateFile($tenant, (string) $this->option('type'), (string) $this->option('file'), null, $this->option('branch') ? (int) $this->option('branch') : null, $this->option('idempotency-key') ?: null);
        } else {
            $actor = User::query()->where('is_platform_admin', true)->when($this->option('actor'), fn ($q) => $q->whereKey((int) $this->option('actor')))->first();
            if ($actor === null) {
                throw ValidationException::withMessages(['actor' => 'A platform admin actor is required for execute.']);
            }
            $run = $imports->executeFile($tenant, (string) $this->option('type'), (string) $this->option('file'), $actor, $this->option('branch') ? (int) $this->option('branch') : null, (string) $this->option('idempotency-key'), (string) $this->option('reason'));
        }

        $this->line((string) json_encode((new TenantDataImportRunResource($run))->resolve(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return in_array($run->status, ['failed', 'partial_failed'], true) ? self::FAILURE : self::SUCCESS;
    }
}
