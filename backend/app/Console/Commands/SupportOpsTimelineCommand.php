<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SupportOperations\SupportDiagnosticTimelineService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:timeline. Shows a deterministic diagnostic timeline
 * (SUP-R020). No raw payloads. Requires --tenant to focus on a tenant; without
 * one it prints a hint and exits cleanly.
 */
class SupportOpsTimelineCommand extends Command
{
    protected $signature = 'support-ops:timeline {--tenant= : Tenant id or code} {--category=} {--source=} {--since=} {--limit=} {--json}';

    protected $description = 'Show a deterministic, redacted tenant diagnostic timeline.';

    public function handle(SupportDiagnosticTimelineService $timeline): int
    {
        $tenant = $this->resolve($this->option('tenant'));
        if ($tenant === null) {
            $this->line('No tenant specified (or not found); pass --tenant=<id|code>.');

            return self::SUCCESS;
        }

        $data = $timeline->build($tenant, [
            'category' => $this->option('category'),
            'source' => $this->option('source'),
            'since' => $this->option('since'),
            'limit' => $this->option('limit') !== null ? (int) $this->option('limit') : null,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Diagnostic timeline for tenant #'.$tenant->id.' ('.$data['count'].' events)');
        foreach ($data['events'] as $e) {
            $this->line(sprintf('  %s [%s/%s] %s — %s', $e['at'] ?? '-', $e['source'], $e['category'], $e['code'], $e['summary']));
        }

        return self::SUCCESS;
    }

    private function resolve(?string $value): ?Tenant
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value)
            ? Tenant::query()->find((int) $value)
            : Tenant::query()->where('code', $value)->first();
    }
}
