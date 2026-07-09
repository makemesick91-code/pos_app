<?php

namespace App\Services\DataImport;

class ImportTemplateService
{
    public function list(): array
    {
        return collect(config('import_governance.import_types', []))
            ->map(fn (string $type) => $this->metadata($type))
            ->values()
            ->all();
    }

    public function metadata(string $type): array
    {
        return [
            'type' => $type,
            'format' => 'csv',
            'headers' => $this->headers($type),
            'xlsx_supported' => (bool) config('import_governance.xlsx.supported', false),
            'xlsx_deferred_reason' => config('import_governance.xlsx.deferred_reason'),
        ];
    }

    public function csv(string $type): string
    {
        return implode(',', $this->headers($type))."\n";
    }

    public function headers(string $type): array
    {
        $headers = (array) config('import_governance.required_headers.'.$type, []);
        if ($headers === []) {
            throw new \InvalidArgumentException('Unsupported import type.');
        }

        return $headers;
    }
}
