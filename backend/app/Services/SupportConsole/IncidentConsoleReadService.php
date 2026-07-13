<?php

namespace App\Services\SupportConsole;

use App\Models\ProductionIncident;
use App\Models\TenantSupportIncident;
use App\Services\SupportOperations\SupportRedactor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * UIX-6 — read adapter for the Incident Console on two surfaces:
 *  - Platform Admin (`/admin/incidents`): platform-global {@see ProductionIncident}
 *    lifecycle (P0..P4, OPEN..CLOSED/ACCEPTED_RISK).
 *  - Tenant Owner (`/owner/support/incidents/{id}`): STRICTLY tenant-scoped, from
 *    the tenant's own {@see TenantSupportIncident} records only.
 *
 * Incident severity, status, impact, and lifecycle come verbatim from the
 * canonical models (UIX6-R014); nothing is recomputed. The console is read-only
 * (UIX6-R015/R016) — there is no write path here. All free text is passed
 * through {@see SupportRedactor} (UIX6-R009/R019), and the owner surface never
 * exposes internal notes, the platform-wide affected list, evidence payloads, or
 * infrastructure identifiers (UIX6-R005/R010).
 */
class IncidentConsoleReadService
{
    /** Whitelisted admin incident sort columns (UIX6-R022/R025). */
    private const INCIDENT_SORTS = ['detected_at', 'severity', 'status', 'created_at'];

    public function __construct(private readonly SupportRedactor $redactor) {}

    /**
     * Paginated, bounded, filtered platform incident list for the admin surface.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<ProductionIncident>
     */
    public function paginateProductionIncidents(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', self::INCIDENT_SORTS, true) ? $filters['sort'] : 'detected_at';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(10, min((int) ($filters['per_page'] ?? 20), 50));

        $query = ProductionIncident::query();

        if (in_array($filters['status'] ?? null, ProductionIncident::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (in_array($filters['severity'] ?? null, ProductionIncident::SEVERITIES, true)) {
            $query->where('severity', $filters['severity']);
        }
        if (($filters['open_only'] ?? false) === true) {
            $query->whereIn('status', ProductionIncident::OPEN_STATUSES);
        }

        return $query->orderBy($sort, $direction)->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    public function findProductionIncident(int $id): ?ProductionIncident
    {
        return ProductionIncident::query()->find($id);
    }

    /**
     * Present a platform incident for the admin detail view — safe fields only,
     * free text redacted, evidence exposed as presence-not-payload.
     *
     * @return array<string, mixed>
     */
    public function presentProductionIncident(ProductionIncident $incident): array
    {
        return [
            'reference' => $incident->incident_reference,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'area' => $incident->area,
            'impact' => $this->redactor->redactText($incident->impact, 500),
            'title' => $this->redactor->redactText($incident->title, 300),
            'description' => $this->redactor->redactText($incident->description, 2000),
            'resolution_summary' => $this->redactor->redactText($incident->resolution_summary, 2000),
            'tenant_id' => $incident->tenant_id,
            'is_open' => $incident->isOpen(),
            'is_accepted_risk' => $incident->isAcceptedRisk(),
            'sla_breached' => $incident->isSlaBreached(),
            'has_evidence' => $incident->evidence_reference !== null && $incident->evidence_reference !== '',
            'detected_at' => optional($incident->detected_at)->toIso8601String(),
            'started_at' => optional($incident->started_at)->toIso8601String(),
            'resolved_at' => optional($incident->resolved_at)->toIso8601String(),
            'closed_at' => optional($incident->closed_at)->toIso8601String(),
            'sla_due_at' => optional($incident->sla_due_at)->toIso8601String(),
        ];
    }

    /**
     * Tenant-scoped list of the tenant's OWN support incidents (owner surface).
     * Deny-by-default: constrained to the owner's tenant id (UIX6-R004/R021).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tenantIncidents(int $tenantId, int $limit = 25): array
    {
        return TenantSupportIncident::query()
            ->forTenant($tenantId)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn (TenantSupportIncident $i) => $i->toSafeArray())
            ->all();
    }

    /**
     * Resolve one tenant support incident within the owner's tenant, or null
     * for a foreign/unknown id — the caller renders 404 (UIX6-R008). Never uses
     * implicit route-model binding.
     */
    public function findTenantIncident(int $tenantId, int $id): ?TenantSupportIncident
    {
        return TenantSupportIncident::query()
            ->forTenant($tenantId)
            ->find($id);
    }

    /**
     * Present a tenant support incident for the OWNER detail view. Only the
     * canonical `_safe` fields are exposed — never internal notes, assignee
     * identity, or platform context (UIX6-R005/R010).
     *
     * @return array<string, mixed>
     */
    public function presentTenantIncident(TenantSupportIncident $incident): array
    {
        $safe = $incident->toSafeArray();

        return [
            'incident_number' => $safe['incident_number'],
            'category' => $safe['category'],
            'severity' => $safe['severity'],
            'status' => $safe['status'],
            'title' => $safe['title'],
            'summary' => $safe['summary'],
            'is_terminal' => $incident->isTerminal(),
            'opened_at' => $safe['opened_at'],
            'resolved_at' => $safe['resolved_at'],
            'closed_at' => $safe['closed_at'],
        ];
    }
}
