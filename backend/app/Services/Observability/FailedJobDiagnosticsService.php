<?php

namespace App\Services\Observability;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sprint 36 — failed job diagnostics (OBS-R009).
 *
 * Summarizes the `failed_jobs` table grouped by a SAFE reason code derived from
 * the job class name + connection/queue. It NEVER returns the raw payload, the
 * exception message, or the stack trace — only a redacted job-class label and a
 * count. Tolerates a missing table so it is CI-safe on the `sync` driver.
 */
class FailedJobDiagnosticsService
{
    public function __construct(private readonly ObservabilityRedactor $redactor) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(int $limit = 100): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['total' => 0, 'groups' => [], 'table_present' => false];
        }

        $limit = max(1, min($limit, 500));
        $groups = [];
        $total = 0;

        try {
            $rows = DB::table('failed_jobs')->orderByDesc('id')->limit($limit)->get();
            foreach ($rows as $row) {
                $total++;
                $label = $this->safeJobLabel($row);
                $groups[$label] ??= ['job_label' => $label, 'count' => 0, 'queues' => []];
                $groups[$label]['count']++;
                $queue = isset($row->queue) ? (string) $row->queue : 'default';
                if (! in_array($queue, $groups[$label]['queues'], true)) {
                    $groups[$label]['queues'][] = $queue;
                }
            }
        } catch (Throwable) {
            return ['total' => 0, 'groups' => [], 'table_present' => true];
        }

        // Deterministic order: most frequent first, then label.
        $ordered = array_values($groups);
        usort($ordered, function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp($a['job_label'], $b['job_label']);
        });

        return ['total' => $total, 'groups' => $ordered, 'table_present' => true];
    }

    /**
     * A single redacted, safe drilldown row (never the payload/exception).
     *
     * @return array<string, mixed>|null
     */
    public function drilldown(int|string $id): ?array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return null;
        }

        $row = DB::table('failed_jobs')->where('id', $id)->orWhere('uuid', (string) $id)->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'uuid' => $row->uuid ?? null,
            'job_label' => $this->safeJobLabel($row),
            'connection' => isset($row->connection) ? (string) $row->connection : null,
            'queue' => isset($row->queue) ? (string) $row->queue : null,
            'failed_at' => isset($row->failed_at) ? (string) $row->failed_at : null,
            // A short, redacted, secret-free exception CLASS/summary — never the
            // full message or stack.
            'exception_class' => $this->safeExceptionClass($row),
        ];
    }

    private function safeJobLabel(object $row): string
    {
        $payload = isset($row->payload) ? (string) $row->payload : '';
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? ($decoded['displayName'] ?? ($decoded['job'] ?? 'unknown')) : 'unknown';

        return (string) ($this->redactor->redactText(class_basename((string) $name), 120) ?? 'unknown');
    }

    private function safeExceptionClass(object $row): ?string
    {
        $exception = isset($row->exception) ? (string) $row->exception : '';
        if ($exception === '') {
            return null;
        }
        // First token before the first ':' is the exception class name; the rest
        // (message + stack) is discarded to guarantee no secret/PII leaks.
        $firstLine = strtok($exception, "\n");
        $class = strtok((string) $firstLine, ':');

        return $this->redactor->redactText(class_basename((string) $class), 120);
    }
}
