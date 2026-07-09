<?php

namespace App\Services\DataImport;

class ImportGovernanceAuditService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public function evaluate(): array
    {
        return [
            $this->rulesPresentSignal(),
            $this->defaultsSignal(),
            $this->formatsSignal(),
            $this->servicesSignal(),
            $this->docsSignal(),
        ];
    }

    private function rulesPresentSignal(): array
    {
        $missing = [];
        $rules = (array) config('import_governance.rules', []);
        for ($i = 1; $i <= 34; $i++) {
            $code = sprintf('IMP-R%03d', $i);
            if (! array_key_exists($code, $rules)) {
                $missing[] = $code;
            }
        }

        return $this->signal('rules_registry', $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL, $missing === [] ? 'All IMP-R001..IMP-R034 rules are declared.' : 'Missing rules: '.implode(', ', $missing).'.');
    }

    private function defaultsSignal(): array
    {
        $ok = config('import_governance.dry_run_default') === true
            && config('import_governance.tenant_side_import_enabled') === false
            && config('import_governance.execute_requires_explicit_flag') === true
            && config('import_governance.execute_requires_reason') === true;

        return $this->signal('safe_defaults', $ok ? self::STATUS_PASS : self::STATUS_FAIL, $ok ? 'Dry-run/admin/reason defaults are safe.' : 'One or more safe defaults are not enforced.');
    }

    private function formatsSignal(): array
    {
        $csv = in_array('csv', (array) config('import_governance.supported_formats'), true);
        $xlsxSafe = config('import_governance.xlsx.supported') === true || config('import_governance.xlsx.deferred_reason') !== null;

        return $this->signal('format_policy', $csv && $xlsxSafe ? self::STATUS_PASS : self::STATUS_FAIL, $csv && $xlsxSafe ? 'CSV supported; XLSX governed/deferred safely.' : 'Format policy is incomplete.');
    }

    private function servicesSignal(): array
    {
        $classes = [
            TenantDataImportService::class,
            ImportFileParserService::class,
            ImportTemplateService::class,
            ImportValidationService::class,
            ImportIdempotencyService::class,
            CategoryImportService::class,
            ProductImportService::class,
            SupplierImportService::class,
            CustomerImportService::class,
            InitialStockImportService::class,
            PriceImportService::class,
            PaymentMethodSettingsImportService::class,
            TenantBootstrapPackService::class,
            ImportRollbackService::class,
            ImportAuditService::class,
            ImportSupportBridgeService::class,
            ImportObservabilityBridgeService::class,
            ImportRedactor::class,
        ];
        $missing = array_values(array_filter($classes, fn ($class) => ! class_exists($class)));

        return $this->signal('services_wired', $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL, $missing === [] ? 'All Sprint 37 import services are present.' : 'Missing services: '.implode(', ', $missing).'.');
    }

    private function docsSignal(): array
    {
        $missing = [];
        foreach ((array) config('import_governance.required_docs', []) as $doc) {
            if (! is_file(dirname(base_path()).'/'.$doc) && ! is_file(base_path($doc))) {
                $missing[] = $doc;
            }
        }

        return $this->signal('docs_contract', $missing === [] ? self::STATUS_PASS : self::STATUS_WARN, $missing === [] ? 'Required Sprint 37 docs are present.' : 'Missing docs: '.implode(', ', $missing).'.');
    }

    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
