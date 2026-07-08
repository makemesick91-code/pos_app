<?php

namespace App\Services\ExportGovernance;

use Illuminate\Routing\Router;

/**
 * Sprint 29 — server-side export-like route discovery (EGC-R002). Scans the
 * registered route table for routes that produce a downloadable/export artifact
 * and classifies each as registered (in the export governance registry) or an
 * unregistered gap. Detection is deterministic: a route is export-like when its
 * URI ends with a configured export extension OR any dot/slash path segment is an
 * exact export/download token. Hyphenated governance/admin summary endpoints are
 * never mistaken for exports, and a small ignore list keeps read-only export
 * governance summaries off the scan.
 */
class ExportRouteDiscoveryService
{
    public function __construct(
        private readonly Router $router,
        private readonly ExportRouteRegistry $registry,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function discover(): array
    {
        $extensions = (array) config('export_governance.discovery.export_extensions', ['.csv', '.xlsx', '.xls', '.pdf']);
        $segments = array_map('strtolower', (array) config('export_governance.discovery.export_segments', ['export', 'exports', 'download', 'downloads']));
        $ignore = (array) config('export_governance.discovery.ignore_signatures', []);

        $found = [];
        foreach ($this->router->getRoutes() as $route) {
            $uri = $route->uri();
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $signature = $method.' '.$uri;
                if (in_array($signature, $ignore, true)) {
                    continue;
                }
                if (! $this->isExportLike($uri, $extensions, $segments)) {
                    continue;
                }

                $registered = $this->registry->isRegistered($signature);
                $meta = $this->registry->find($signature);
                $found[$signature] = [
                    'signature' => $signature,
                    'method' => $method,
                    'uri' => $uri,
                    'action' => $route->getActionName(),
                    'registered' => $registered,
                    'disposition' => $registered ? ($meta['disposition'] ?? null) : null,
                ];
            }
        }

        ksort($found);

        return array_values($found);
    }

    /**
     * Export-like routes that are NOT registered in the governance registry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function unregistered(): array
    {
        return array_values(array_filter($this->discover(), fn ($r) => $r['registered'] === false));
    }

    /**
     * @param array<int, string> $extensions
     * @param array<int, string> $segments
     */
    private function isExportLike(string $uri, array $extensions, array $segments): bool
    {
        $lower = strtolower($uri);

        foreach ($extensions as $ext) {
            if (str_ends_with($lower, strtolower((string) $ext))) {
                return true;
            }
        }

        // Split only on `/` and `.` so hyphenated tokens (report-export-metering,
        // export-governance) are never treated as an export segment.
        foreach (preg_split('#[/.]#', $lower) ?: [] as $seg) {
            if ($seg !== '' && in_array($seg, $segments, true)) {
                return true;
            }
        }

        return false;
    }
}
