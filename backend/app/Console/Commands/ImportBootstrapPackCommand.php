<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\DataImport\TenantBootstrapPackService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportBootstrapPackCommand extends Command
{
    protected $signature = 'import:bootstrap-pack {--tenant=} {--branch=} {--file=} {--idempotency-key=} {--execute} {--reason=} {--actor=}';
    protected $description = 'Dry-run or execute a first-tenant bootstrap pack.';

    public function handle(TenantBootstrapPackService $bootstrap): int
    {
        $actor = User::query()->where('is_platform_admin', true)->when($this->option('actor'), fn ($q) => $q->whereKey((int) $this->option('actor')))->first();
        if ($this->option('execute') && $actor === null) {
            throw ValidationException::withMessages(['actor' => 'A platform admin actor is required for bootstrap execute.']);
        }

        $summary = $bootstrap->run(Tenant::findOrFail((int) $this->option('tenant')), (string) $this->option('file'), $actor, $this->option('branch') ? (int) $this->option('branch') : null, (string) $this->option('idempotency-key'), (bool) $this->option('execute'), $this->option('reason') ? (string) $this->option('reason') : null);
        $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
