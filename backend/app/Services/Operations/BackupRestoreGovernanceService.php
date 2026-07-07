<?php

namespace App\Services\Operations;

/**
 * Sprint 19 — backup/restore governance check.
 *
 * Verifies that the backup/restore governance documentation exists and covers
 * the required sections (backup ownership, backup frequency, restore rehearsal
 * cadence, rollback relation, backup verification evidence) and that the Sprint
 * 13 backup/restore runbook and Sprint 18 backup/restore handover are present.
 * Produces a GO/WATCH/NO_GO decision.
 *
 * This is a governance/documentation check only. It never executes a real
 * backup, never executes a real restore, and never prints DB credentials.
 */
class BackupRestoreGovernanceService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    private const GOVERNANCE_DOC = 'docs/operations/backup-restore-governance.md';

    /** Supporting evidence docs from prior sprints (a warning when missing). */
    private const SUPPORTING_DOCS = [
        'docs/release/backup-restore-runbook.md',
        'docs/handover/backup-restore-handover.md',
    ];

    /**
     * @return array<string,mixed>
     */
    public function evaluate(): array
    {
        $checks = [
            $this->governanceDocCheck(),
            $this->requiredSectionsCheck(),
            ...$this->supportingDocChecks(),
        ];

        return [
            'decision' => $this->decision($checks),
            'checks' => $checks,
        ];
    }

    /**
     * @param array<int,array{status:string}> $checks
     */
    private function decision(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::STATUS_FAIL, $statuses, true)) {
            return self::DECISION_NO_GO;
        }

        if (in_array(self::STATUS_WARN, $statuses, true)) {
            return self::DECISION_WATCH;
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function check(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function governanceDocCheck(): array
    {
        return $this->docExists(self::GOVERNANCE_DOC)
            ? $this->check('governance_doc', self::STATUS_PASS, 'Backup/restore governance doc present.')
            : $this->check('governance_doc', self::STATUS_FAIL, 'Missing backup/restore governance doc: '.self::GOVERNANCE_DOC);
    }

    private function requiredSectionsCheck(): array
    {
        if (! $this->docExists(self::GOVERNANCE_DOC)) {
            return $this->check('required_sections', self::STATUS_FAIL, 'Backup/restore governance doc missing.');
        }

        $contents = strtolower((string) @file_get_contents($this->repoRoot().'/'.self::GOVERNANCE_DOC));
        $required = (array) config('production_operations.backup_restore_required_sections', []);
        $missing = [];
        foreach ($required as $section) {
            if (! str_contains($contents, strtolower((string) $section))) {
                $missing[] = $section;
            }
        }

        return $missing === []
            ? $this->check('required_sections', self::STATUS_PASS, 'All required backup/restore sections documented.')
            : $this->check('required_sections', self::STATUS_WARN, 'Backup/restore sections incomplete: '.implode(', ', $missing));
    }

    /**
     * @return array<int,array{key:string,status:string,message:string}>
     */
    private function supportingDocChecks(): array
    {
        $out = [];
        foreach (self::SUPPORTING_DOCS as $doc) {
            $out[] = $this->docExists($doc)
                ? $this->check('supporting:'.basename($doc), self::STATUS_PASS, 'Present: '.$doc)
                : $this->check('supporting:'.basename($doc), self::STATUS_WARN, 'Missing supporting doc: '.$doc);
        }

        return $out;
    }

    private function docExists(string $path): bool
    {
        return is_file($this->repoRoot().'/'.ltrim($path, '/'));
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
