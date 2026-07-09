<?php

namespace App\Services\DataImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ImportFileParserService
{
    public function __construct(private readonly ImportIdempotencyService $idempotency) {}

    /**
     * @return array{format: string, file_hash: string, original_filename_hash: string|null, rows: array<int, array<string, string|null>>}
     */
    public function parse(string|UploadedFile $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $name = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);

        if (! is_string($path) || ! is_file($path)) {
            throw ValidationException::withMessages(['file' => 'Import file was not found.']);
        }

        $size = filesize($path) ?: 0;
        if ($size > (int) config('import_governance.max_file_size_bytes')) {
            throw ValidationException::withMessages(['file' => 'Import file exceeds the governed size limit.']);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($extension, ['xlsx', 'xlsm', 'xls'], true)) {
            throw ValidationException::withMessages(['file' => (string) config('import_governance.xlsx.deferred_reason')]);
        }
        if ($extension !== 'csv') {
            throw ValidationException::withMessages(['file' => 'Only CSV import is supported in Sprint 37.']);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Import file could not be opened.']);
        }

        $headers = null;
        $rows = [];
        $rowLimit = (int) config('import_governance.max_rows', 1000);
        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }

            if (count($rows) >= $rowLimit) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'Import row count exceeds the governed row limit.']);
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = isset($data[$index]) ? trim((string) $data[$index]) : null;
            }
            if (array_filter($row, fn ($value) => $value !== null && $value !== '') !== []) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return [
            'format' => 'csv',
            'file_hash' => $this->idempotency->fileHash($path),
            'original_filename_hash' => hash('sha256', $name),
            'rows' => $rows,
        ];
    }

    private function normalizeHeaders(array $headers): array
    {
        return array_map(function (mixed $header): string {
            $header = strtolower(trim((string) $header));
            $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';
            return trim($header, '_');
        }, $headers);
    }
}
