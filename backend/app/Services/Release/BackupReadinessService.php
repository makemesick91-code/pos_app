<?php

namespace App\Services\Release;

/**
 * Sprint 13 — Production Readiness & Release Hardening Foundation.
 *
 * Validates that the environment is ready to take a pre-release backup and
 * exposes safe, credential-free command/checklist templates. It performs NO
 * destructive restore and stores NO real credentials — the DATABASE_URL
 * placeholder is resolved from the environment at runbook execution time, not
 * here. See docs/release/backup-restore-runbook.md.
 */
class BackupReadinessService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * @return array{overall_status:string, checks:array<int,array{key:string,status:string,message:string}>, templates:array<string,string>}
     */
    public function evaluate(): array
    {
        $checks = [
            $this->backupDirectoryCheck(),
            $this->storageWritableCheck(),
        ];

        $statuses = array_column($checks, 'status');
        $overall = in_array(self::STATUS_FAIL, $statuses, true)
            ? self::STATUS_FAIL
            : (in_array(self::STATUS_WARN, $statuses, true) ? self::STATUS_WARN : self::STATUS_PASS);

        return [
            'overall_status' => $overall,
            'checks' => $checks,
            'templates' => $this->templates(),
        ];
    }

    public function backupDirectory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Safe, credential-free templates. Real credentials are supplied by the
     * operator through the environment at execution time.
     *
     * @return array<string,string>
     */
    public function templates(): array
    {
        return [
            'database_backup' => 'pg_dump "$DATABASE_URL" > backups/backup_$(date +%Y%m%d_%H%M%S).sql',
            'database_restore_rehearsal' => 'psql "$STAGING_DATABASE_URL" < backups/backup_YYYYMMDD_HHMMSS.sql',
            'storage_backup' => 'tar -czf backups/storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/app',
        ];
    }

    private function backupDirectoryCheck(): array
    {
        $dir = $this->backupDirectory();
        $parent = dirname($dir);

        if (is_dir($dir)) {
            return ['key' => 'backup.directory', 'status' => self::STATUS_PASS, 'message' => 'Backup directory exists.'];
        }

        return is_writable($parent)
            ? ['key' => 'backup.directory', 'status' => self::STATUS_PASS, 'message' => 'Backup directory can be created (parent writable).']
            : ['key' => 'backup.directory', 'status' => self::STATUS_WARN, 'message' => 'Backup directory parent is not writable.'];
    }

    private function storageWritableCheck(): array
    {
        return is_writable(storage_path('app'))
            ? ['key' => 'backup.storage_writable', 'status' => self::STATUS_PASS, 'message' => 'Storage path is writable for backups.']
            : ['key' => 'backup.storage_writable', 'status' => self::STATUS_FAIL, 'message' => 'Storage path is not writable.'];
    }
}
