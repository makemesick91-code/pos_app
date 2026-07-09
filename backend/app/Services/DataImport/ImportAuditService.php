<?php

namespace App\Services\DataImport;

use App\Models\TenantDataImportRun;
use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Illuminate\Validation\ValidationException;

class ImportAuditService
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
        private readonly ImportRedactor $redactor,
    ) {}

    public function assertReasonCode(?string $reasonCode): string
    {
        $reasonCode = is_string($reasonCode) ? trim($reasonCode) : '';
        if ($reasonCode === '') {
            throw ValidationException::withMessages(['reason_code' => 'A governed reason_code is required.']);
        }

        return $reasonCode;
    }

    public function record(User $actor, TenantDataImportRun $run, string $action, ?string $reasonCode = null, array $metadata = []): void
    {
        $this->audit->log(
            actor: $actor,
            action: 'IMPORT_'.strtoupper($action),
            targetType: TenantDataImportRun::class,
            targetId: (int) $run->id,
            tenantId: (int) $run->tenant_id,
            metadata: array_merge($this->redactor->redact($metadata), ['reason_code' => $reasonCode]),
        );
    }
}
