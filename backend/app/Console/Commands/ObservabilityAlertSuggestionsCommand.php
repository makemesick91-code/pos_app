<?php

namespace App\Console\Commands;

use App\Services\Observability\ObservabilityIncidentSuggestionService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:alert-suggestions. Lists alert/incident suggestions
 * (read-only default). --generate creates SUGGESTIONS ONLY from open anomaly
 * events; it never auto-creates a support incident or mutates tenant state.
 * Incident creation is only ever done via the audited accept API. --json.
 */
class ObservabilityAlertSuggestionsCommand extends Command
{
    protected $signature = 'observability:alert-suggestions {--generate : Generate suggestions from open anomalies (suggestions only)} {--status=suggested : Filter list by status (or "all")} {--json : Output JSON}';

    protected $description = 'List alert/incident suggestions; --generate creates suggestions only (no tenant mutation).';

    public function handle(ObservabilityIncidentSuggestionService $service): int
    {
        $generated = null;
        if ($this->option('generate')) {
            $generated = $service->generateFromAnomalies();
        }

        $list = $service->list((string) $this->option('status'));

        if ($this->option('json')) {
            $this->line((string) json_encode(['generated' => $generated, 'suggestions' => $list], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($generated !== null) {
            $this->line("Generated: created={$generated['created']} skipped={$generated['skipped']}");
        }
        $this->line('Alert suggestions ('.count($list).'):');
        foreach ($list as $s) {
            $this->line("  [{$s['severity']}/{$s['status']}] #{$s['id']} {$s['suggested_action']} (tenant=".($s['tenant_id'] ?? 'app').')');
        }

        return self::SUCCESS;
    }
}
