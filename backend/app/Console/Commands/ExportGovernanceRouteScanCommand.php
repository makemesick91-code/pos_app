<?php

namespace App\Console\Commands;

use App\Services\ExportGovernance\ExportRouteDiscoveryService;
use Illuminate\Console\Command;

/**
 * Sprint 29 — export-governance:route-scan. Read-only server-side discovery of
 * every export-like route and whether it is registered in the governance registry
 * (EGC-R002). Informative by default (exit 0); with --strict it fails when any
 * discovered export-like route is unregistered. Never prints secrets.
 */
class ExportGovernanceRouteScanCommand extends Command
{
    protected $signature = 'export-governance:route-scan {--json : Output JSON} {--strict : Fail if any export-like route is unregistered}';

    protected $description = 'Discover export-like routes and show registered vs unregistered coverage.';

    public function handle(ExportRouteDiscoveryService $discovery): int
    {
        $routes = $discovery->discover();
        $unregistered = array_values(array_filter($routes, fn ($r) => $r['registered'] === false));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'discovered' => $routes,
                'unregistered' => $unregistered,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Export-like route scan ('.count($routes).' discovered):');
            foreach ($routes as $r) {
                $mark = $r['registered'] ? '[registered:'.$r['disposition'].']' : '[UNREGISTERED]';
                $this->line("  {$mark} {$r['signature']}");
            }
            $this->line('Unregistered export-like routes: '.count($unregistered));
        }

        if ($this->option('strict') && $unregistered !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
