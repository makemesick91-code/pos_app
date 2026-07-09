<?php

namespace App\Services\DataImport;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class TenantBootstrapPackService
{
    public function __construct(private readonly TenantDataImportService $imports) {}

    /**
     * Bootstrap pack CSV uses a section column. Each section is delegated to the
     * central TenantDataImportService so Sprint 37 mutation rules stay singular.
     *
     * @return array<string, mixed>
     */
    public function run(Tenant $tenant, string|UploadedFile $file, ?User $actor, ?int $branchId, string $idempotencyKey, bool $execute = false, ?string $reasonCode = null): array
    {
        $tempFiles = $this->splitBySection($file);
        $runs = [];

        foreach ($tempFiles as $section => $path) {
            $runs[$section] = $execute
                ? $this->imports->executeFile($tenant, $section, $path, $actor, $branchId, $idempotencyKey.':'.$section, (string) $reasonCode)
                : $this->imports->validateFile($tenant, $section, $path, $actor, $branchId, $idempotencyKey.':'.$section);
        }

        return [
            'mode' => $execute ? 'execute' : 'dry_run',
            'provisioning_integrated' => true,
            'runs' => collect($runs)->map(fn ($run) => ['id' => $run->id, 'type' => $run->import_type, 'status' => $run->status])->all(),
        ];
    }

    private function splitBySection(string|UploadedFile $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $handle = fopen((string) $path, 'rb');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);
        $sectionIndex = array_search('section', $headers, true);
        $groups = [];

        while (($row = fgetcsv($handle)) !== false) {
            $section = $sectionIndex === false ? null : ($row[$sectionIndex] ?? null);
            if (! in_array($section, ['category', 'product', 'supplier', 'customer', 'initial_stock', 'price', 'payment_method', 'default_settings'], true)) {
                continue;
            }
            $groups[$section][] = $row;
        }
        fclose($handle);

        $paths = [];
        foreach ($groups as $section => $rows) {
            $sectionHeaders = array_values(array_diff($headers, ['section']));
            $tmp = tempnam(sys_get_temp_dir(), 's37_'.$section.'_').'.csv';
            $out = fopen($tmp, 'wb');
            fputcsv($out, $sectionHeaders);
            foreach ($rows as $row) {
                $values = [];
                foreach ($headers as $index => $header) {
                    if ($header !== 'section') {
                        $values[] = $row[$index] ?? null;
                    }
                }
                fputcsv($out, $values);
            }
            fclose($out);
            $paths[$section] = $tmp;
        }

        return $paths;
    }
}
