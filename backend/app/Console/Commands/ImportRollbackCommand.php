<?php

namespace App\Console\Commands;

use App\Models\TenantDataImportRun;
use App\Models\User;
use App\Services\DataImport\ImportRollbackService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportRollbackCommand extends Command
{
    protected $signature = 'import:rollback {--run=} {--execute} {--reason=} {--actor=}';
    protected $description = 'Rollback safe records created by an import run; dry-run by default.';

    public function handle(ImportRollbackService $rollback): int
    {
        $actor = User::query()->where('is_platform_admin', true)->when($this->option('actor'), fn ($q) => $q->whereKey((int) $this->option('actor')))->first();
        if ($this->option('execute') && $actor === null) {
            throw ValidationException::withMessages(['actor' => 'A platform admin actor is required for rollback execute.']);
        }

        $summary = $rollback->rollback(TenantDataImportRun::findOrFail((int) $this->option('run')), $actor, (bool) $this->option('execute'), $this->option('reason') ? (string) $this->option('reason') : null);
        $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
