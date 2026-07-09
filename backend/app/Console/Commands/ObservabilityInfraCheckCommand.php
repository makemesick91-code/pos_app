<?php

namespace App\Console\Commands;

use App\Services\Observability\InfrastructureHealthCheckService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:infra-check. Checks database/cache/storage/config
 * safely. Reports status + driver/store/disk NAMES only — never a credential,
 * cache key/value, or raw path. Supports --json.
 */
class ObservabilityInfraCheckCommand extends Command
{
    protected $signature = 'observability:infra-check {--json : Output JSON}';

    protected $description = 'Check database/cache/storage/config health without exposing secrets.';

    public function handle(InfrastructureHealthCheckService $infra): int
    {
        $result = $infra->check();

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Infrastructure: '.$result['status']);
            foreach ($result['checks'] as $name => $check) {
                $this->line("  [{$check['status']}] {$name}");
            }
        }

        return $result['status'] === InfrastructureHealthCheckService::STATUS_OK ? self::SUCCESS : self::FAILURE;
    }
}
