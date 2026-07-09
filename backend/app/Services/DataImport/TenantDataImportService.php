<?php

namespace App\Services\DataImport;

use App\Models\Tenant;
use App\Models\TenantDataImportRow;
use App\Models\TenantDataImportRun;
use App\Models\User;
use App\Services\Entitlements\EntitlementAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantDataImportService
{
    public function __construct(
        private readonly ImportFileParserService $parser,
        private readonly ImportValidationService $validator,
        private readonly ImportIdempotencyService $idempotency,
        private readonly ImportRedactor $redactor,
        private readonly ImportAuditService $audit,
        private readonly EntitlementAccessService $entitlements,
        private readonly CategoryImportService $categories,
        private readonly ProductImportService $products,
        private readonly SupplierImportService $suppliers,
        private readonly CustomerImportService $customers,
        private readonly InitialStockImportService $stock,
        private readonly PriceImportService $prices,
        private readonly PaymentMethodSettingsImportService $paymentSettings,
    ) {}

    public function validateFile(Tenant $tenant, string $type, string|UploadedFile $file, ?User $actor = null, ?int $branchId = null, ?string $idempotencyKey = null): TenantDataImportRun
    {
        return $this->process($tenant, $type, $file, $actor, $branchId, $idempotencyKey ?? 'dry-run-'.uniqid('', true), false, null);
    }

    public function executeFile(Tenant $tenant, string $type, string|UploadedFile $file, User $actor, ?int $branchId, string $idempotencyKey, string $reasonCode): TenantDataImportRun
    {
        $this->audit->assertReasonCode($reasonCode);

        return $this->process($tenant, $type, $file, $actor, $branchId, $idempotencyKey, true, $reasonCode);
    }

    public function retry(TenantDataImportRun $run, User $actor, string $reasonCode): TenantDataImportRun
    {
        $this->audit->assertReasonCode($reasonCode);
        $run->update(['status' => TenantDataImportRun::STATUS_EXECUTING, 'mode' => TenantDataImportRun::MODE_EXECUTE]);

        return DB::transaction(function () use ($run, $actor, $reasonCode) {
            foreach ($run->rows()->whereIn('status', [TenantDataImportRow::STATUS_VALID, TenantDataImportRow::STATUS_FAILED])->orderBy('row_number')->get() as $row) {
                $this->applyRow($run, $row, $actor, $reasonCode);
            }
            $this->refreshSummary($run);
            $this->audit->record($actor, $run, 'retried', $reasonCode);

            return $run->refresh();
        });
    }

    private function process(Tenant $tenant, string $type, string|UploadedFile $file, ?User $actor, ?int $branchId, string $idempotencyKey, bool $execute, ?string $reasonCode): TenantDataImportRun
    {
        $this->assertType($type);
        $parsed = $this->parser->parse($file);
        $runKey = $this->idempotency->runKey((int) $tenant->id, $type, $idempotencyKey);

        if ($execute) {
            $decision = $this->entitlements->canWrite($tenant, $actor, 'data_import');
            if (! $decision->allowed) {
                $run = $this->firstOrCreateRun($tenant, $type, $branchId, $actor, $parsed, $runKey, $execute);
                if ($actor !== null) {
                    $this->audit->record($actor, $run, 'denied', $reasonCode, ['decision' => $decision->toArray()]);
                }
                throw ValidationException::withMessages(['tenant' => 'Tenant write access is not allowed for import.']);
            }
        }

        return DB::transaction(function () use ($tenant, $type, $branchId, $actor, $parsed, $runKey, $execute, $reasonCode) {
            $run = $this->firstOrCreateRun($tenant, $type, $branchId, $actor, $parsed, $runKey, $execute);
            if ($run->status === TenantDataImportRun::STATUS_COMPLETED && $execute) {
                return $run;
            }

            $run->update([
                'status' => TenantDataImportRun::STATUS_VALIDATING,
                'mode' => $execute ? TenantDataImportRun::MODE_EXECUTE : TenantDataImportRun::MODE_DRY_RUN,
                'started_at' => $run->started_at ?? now(),
            ]);

            $headerErrors = $parsed['rows'] === [] ? [] : $this->validator->validateHeaders($type, $parsed['rows'][0]);
            foreach ($parsed['rows'] as $index => $rawRow) {
                $validation = $headerErrors === [] ? $this->validator->validate((int) $tenant->id, $branchId, $type, $rawRow) : ['valid' => false, 'normalized' => $rawRow, 'errors' => $headerErrors];
                $fingerprint = $this->idempotency->rowFingerprint((int) $tenant->id, $type, $validation['normalized']);
                TenantDataImportRow::query()->updateOrCreate(
                    ['tenant_data_import_run_id' => $run->id, 'row_fingerprint' => $fingerprint],
                    [
                        'tenant_id' => $tenant->id,
                        'row_number' => $index + 2,
                        'row_type' => $type,
                        'status' => $validation['valid'] ? TenantDataImportRow::STATUS_VALID : TenantDataImportRow::STATUS_INVALID,
                        'action' => 'none',
                        'error_code' => $validation['valid'] ? null : ($validation['errors'][0]['code'] ?? 'INVALID'),
                        'error_message_safe' => $validation['valid'] ? null : ($validation['errors'][0]['message'] ?? 'Invalid row.'),
                        'original_row_hash' => hash('sha256', json_encode($rawRow, JSON_UNESCAPED_SLASHES)),
                        'normalized_json' => $this->redactor->safeRow($validation['normalized']),
                        'metadata_json' => ['validated_at' => now()->toISOString()],
                    ],
                );
            }

            $this->refreshSummary($run);
            if ($run->invalid_rows > 0) {
                $run->update(['status' => TenantDataImportRun::STATUS_FAILED, 'failed_at' => now(), 'failure_reason' => 'Import validation failed.']);
                if ($actor !== null) {
                    $this->audit->record($actor, $run, 'failed', $reasonCode);
                }
                return $run->refresh();
            }

            if (! $execute) {
                $run->update(['status' => TenantDataImportRun::STATUS_VALIDATED, 'completed_at' => now()]);
                if ($actor !== null) {
                    $this->audit->record($actor, $run, 'validated', $reasonCode);
                }
                return $run->refresh();
            }

            $run->update(['status' => TenantDataImportRun::STATUS_EXECUTING]);
            foreach ($run->rows()->where('status', TenantDataImportRow::STATUS_VALID)->orderBy('row_number')->get() as $row) {
                $this->applyRow($run, $row, $actor, $reasonCode);
            }

            $this->refreshSummary($run);
            $run->update([
                'status' => $run->failed_rows > 0 ? TenantDataImportRun::STATUS_PARTIAL_FAILED : TenantDataImportRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            if ($actor !== null) {
                $this->audit->record($actor, $run, 'executed', $reasonCode);
            }

            return $run->refresh();
        });
    }

    public function applyRow(TenantDataImportRun $run, TenantDataImportRow $row, ?User $actor, ?string $reasonCode): void
    {
        if (in_array($row->status, [TenantDataImportRow::STATUS_CREATED, TenantDataImportRow::STATUS_UPDATED, TenantDataImportRow::STATUS_SKIPPED], true)) {
            return;
        }

        try {
            $data = (array) $row->normalized_json;
            $reference = 'import:'.$run->id.':row:'.$row->row_fingerprint;
            $result = match ($row->row_type) {
                'category' => $this->categories->apply((int) $run->tenant_id, $run->branch_id, $data),
                'product' => $this->products->apply((int) $run->tenant_id, $run->branch_id, $data),
                'supplier' => $this->suppliers->apply((int) $run->tenant_id, $run->branch_id, $data),
                'customer' => $this->customers->apply((int) $run->tenant_id, $run->branch_id, $data),
                'initial_stock' => $this->stock->apply((int) $run->tenant_id, $run->branch_id, $data, $actor?->id, $reference),
                'price' => $this->prices->apply((int) $run->tenant_id, $run->branch_id, $data),
                'payment_method' => $this->paymentSettings->applyPaymentMethod((int) $run->tenant_id, $run->branch_id, $data),
                'default_settings' => $this->paymentSettings->applyDefaultSetting((int) $run->tenant_id, $run->branch_id, $data),
                default => throw ValidationException::withMessages(['type' => 'Unsupported import type.']),
            };

            /** @var Model $subject */
            $subject = $result['subject'];
            $action = $result['action'];
            $row->update([
                'status' => $action === 'created' ? TenantDataImportRow::STATUS_CREATED : ($action === 'updated' ? TenantDataImportRow::STATUS_UPDATED : TenantDataImportRow::STATUS_SKIPPED),
                'action' => $action,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $row->update([
                'status' => TenantDataImportRow::STATUS_FAILED,
                'action' => 'none',
                'error_code' => 'EXECUTION_FAILED',
                'error_message_safe' => $this->redactor->redactText($e->getMessage()),
                'processed_at' => now(),
            ]);
        }
    }

    private function firstOrCreateRun(Tenant $tenant, string $type, ?int $branchId, ?User $actor, array $parsed, string $runKey, bool $execute): TenantDataImportRun
    {
        return TenantDataImportRun::query()->firstOrCreate(
            ['idempotency_key' => $runKey],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branchId,
                'requested_by_user_id' => $actor?->id,
                'import_type' => $type,
                'source_format' => $parsed['format'],
                'status' => TenantDataImportRun::STATUS_DRAFT,
                'mode' => $execute ? TenantDataImportRun::MODE_EXECUTE : TenantDataImportRun::MODE_DRY_RUN,
                'original_filename_hash' => $parsed['original_filename_hash'],
                'file_hash' => $parsed['file_hash'],
                'rollback_supported' => true,
                'metadata_json' => ['source' => 'sprint37_import'],
            ],
        );
    }

    private function refreshSummary(TenantDataImportRun $run): void
    {
        $counts = $run->rows()
            ->selectRaw("status, COUNT(*) as aggregate")
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $run->forceFill([
            'total_rows' => (int) $run->rows()->count(),
            'valid_rows' => (int) ($counts[TenantDataImportRow::STATUS_VALID] ?? 0),
            'invalid_rows' => (int) ($counts[TenantDataImportRow::STATUS_INVALID] ?? 0),
            'created_rows' => (int) ($counts[TenantDataImportRow::STATUS_CREATED] ?? 0),
            'updated_rows' => (int) ($counts[TenantDataImportRow::STATUS_UPDATED] ?? 0),
            'skipped_rows' => (int) (($counts[TenantDataImportRow::STATUS_SKIPPED] ?? 0) + ($counts[TenantDataImportRow::STATUS_DUPLICATE] ?? 0)),
            'failed_rows' => (int) ($counts[TenantDataImportRow::STATUS_FAILED] ?? 0),
            'summary_json' => [
                'type' => $run->import_type,
                'mode' => $run->mode,
                'safe_counts' => true,
            ],
        ])->save();
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, (array) config('import_governance.import_types', []), true) || $type === 'bootstrap_pack') {
            throw ValidationException::withMessages(['type' => 'Unsupported import type.']);
        }
    }
}
