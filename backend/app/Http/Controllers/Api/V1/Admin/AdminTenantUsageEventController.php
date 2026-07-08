<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\UsageEventResource;
use App\Models\Tenant;
use App\Models\TenantUsageEvent;
use App\Services\UsageEventLedger\UsageEventLedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sprint 27 — platform-admin read-only inspection of a tenant's usage events and
 * per-meter summary (UEL-R013). Always tenant-scoped; there is no runtime
 * update/delete route for the append-only ledger (UEL-R002). Metadata is
 * redacted by the resource (UEL-R003).
 */
class AdminTenantUsageEventController extends Controller
{
    public function __construct(
        private readonly UsageEventLedgerService $ledger,
    ) {}

    public function index(Request $request, Tenant $tenant): AnonymousResourceCollection
    {
        $query = TenantUsageEvent::query()
            ->forTenant((int) $tenant->id)
            ->latest('occurred_at');

        if ($request->filled('meter_key')) {
            $query->forMeter((string) $request->input('meter_key'));
        }
        if ($request->filled('event_key')) {
            $query->where('event_key', (string) $request->input('event_key'));
        }
        if ($request->filled('period_key')) {
            $query->forPeriod((string) $request->input('period_key'));
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));

        return UsageEventResource::collection($query->paginate($perPage));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant): array
    {
        return [
            'data' => [
                'tenant_id' => $tenant->id,
                'meters' => $this->ledger->tenantSummary($tenant),
            ],
        ];
    }
}
