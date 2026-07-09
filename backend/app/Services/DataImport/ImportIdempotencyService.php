<?php

namespace App\Services\DataImport;

class ImportIdempotencyService
{
    public function runKey(int $tenantId, string $importType, string $idempotencyKey): string
    {
        return hash('sha256', $tenantId.'|'.$importType.'|'.trim($idempotencyKey));
    }

    public function rowFingerprint(int $tenantId, string $importType, array $normalizedRow): string
    {
        ksort($normalizedRow);

        return hash('sha256', $tenantId.'|'.$importType.'|'.json_encode($normalizedRow, JSON_UNESCAPED_SLASHES));
    }

    public function fileHash(string $path): string
    {
        return hash_file('sha256', $path) ?: hash('sha256', basename($path));
    }
}
