<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExecuteTenantImportRequest;
use App\Http\Requests\RollbackTenantImportRequest;
use App\Http\Requests\RetryTenantImportRequest;
use App\Http\Requests\ValidateTenantImportRequest;
use App\Http\Resources\TenantDataImportRowResource;
use App\Http\Resources\TenantDataImportRunResource;
use App\Models\Tenant;
use App\Models\TenantDataImportRun;
use App\Services\DataImport\ImportGovernanceAuditService;
use App\Services\DataImport\ImportRollbackService;
use App\Services\DataImport\ImportTemplateService;
use App\Services\DataImport\TenantDataImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTenantDataImportController extends Controller
{
    public function __construct(
        private readonly TenantDataImportService $imports,
        private readonly ImportTemplateService $templates,
        private readonly ImportRollbackService $rollback,
        private readonly ImportGovernanceAuditService $governance,
    ) {}

    public function templates(): JsonResponse
    {
        return response()->json(['data' => $this->templates->list()]);
    }

    public function template(string $type)
    {
        return response($this->templates->csv($type), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$type.'-template.csv"',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TenantDataImportRun::query()->latest();
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', (int) $request->input('tenant_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => TenantDataImportRunResource::collection($query->limit(50)->get())]);
    }

    public function show(TenantDataImportRun $run): JsonResponse
    {
        return response()->json(['data' => new TenantDataImportRunResource($run)]);
    }

    public function rows(TenantDataImportRun $run): JsonResponse
    {
        return response()->json(['data' => TenantDataImportRowResource::collection($run->rows()->orderBy('row_number')->limit(200)->get())]);
    }

    public function validateImport(ValidateTenantImportRequest $request): JsonResponse
    {
        $run = $this->imports->validateFile(
            Tenant::findOrFail((int) $request->input('tenant_id')),
            (string) $request->input('type'),
            $request->file('file'),
            $request->user(),
            $request->integer('branch_id') ?: null,
            $request->input('idempotency_key'),
        );

        return response()->json(['data' => new TenantDataImportRunResource($run)], 201);
    }

    public function executeImport(ExecuteTenantImportRequest $request): JsonResponse
    {
        $run = $this->imports->executeFile(
            Tenant::findOrFail((int) $request->input('tenant_id')),
            (string) $request->input('type'),
            $request->file('file'),
            $request->user(),
            $request->integer('branch_id') ?: null,
            (string) $request->input('idempotency_key'),
            (string) $request->input('reason_code'),
        );

        return response()->json(['data' => new TenantDataImportRunResource($run)], 201);
    }

    public function retry(RetryTenantImportRequest $request, TenantDataImportRun $run): JsonResponse
    {
        return response()->json(['data' => new TenantDataImportRunResource($this->imports->retry($run, $request->user(), (string) $request->input('reason_code')))]);
    }

    public function rollback(RollbackTenantImportRequest $request, TenantDataImportRun $run): JsonResponse
    {
        return response()->json(['data' => $this->rollback->rollback($run, $request->user(), $request->boolean('execute'), $request->input('reason_code'))]);
    }

    public function governance(): JsonResponse
    {
        return response()->json(['data' => $this->governance->evaluate()]);
    }
}
