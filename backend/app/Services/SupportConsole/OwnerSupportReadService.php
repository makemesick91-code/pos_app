<?php

namespace App\Services\SupportConsole;

use App\Services\OwnerConsole\OwnerContext;
use App\Services\SupportOperations\SupportAndroidRuntimeViewerService;
use App\Services\SupportOperations\SupportTenantHealthService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UIX-6 — read adapter for the Tenant Owner Support / Operational view
 * (`/owner/support`). STRICTLY tenant-scoped to the authenticated owner's own
 * tenant, resolved server-side via {@see OwnerContext} — never from request
 * input (UIX6-R004/R007).
 *
 * The owner sees only tenant-safe operational information: their own tenant
 * health summary, sync/import status for their tenant, and their own support
 * incidents. It NEVER exposes platform-global observability, the affected-tenant
 * list, infrastructure identifiers, raw logs, stack traces, or internal notes
 * (UIX6-R005/R010).
 *
 * There is no canonical tenant-facing support-request/ticket service in the
 * domain, so request creation is deliberately NOT offered here; the view shows a
 * truthful "no self-service channel" state (documented as deferred scope), never
 * a fake ticketing engine (UIX6-R016, prompt §20).
 */
class OwnerSupportReadService
{
    public function __construct(
        private readonly SupportTenantHealthService $health,
        private readonly SupportAndroidRuntimeViewerService $androidRuntime,
        private readonly IncidentConsoleReadService $incidents,
    ) {}

    /**
     * The owner support overview view model, entirely tenant-scoped.
     *
     * @return array<string, mixed>
     */
    public function overview(OwnerContext $context): array
    {
        $tenantId = $context->tenantId();

        return [
            'lifecycle' => $context->lifecycle,
            'operational' => $context->operational(),
            'health' => $this->safe(fn () => $this->healthSummary($context)),
            'sync' => $this->safe(fn () => $this->androidRuntime->summary($tenantId)),
            'sync_failures' => $this->safe(fn () => $this->androidRuntime->syncFailures($tenantId)),
            'incidents' => $this->safe(fn () => ['items' => $this->incidents->tenantIncidents($tenantId)]),
            'support_channel_self_service' => false,
        ];
    }

    /**
     * Minimal tenant-safe health summary (matches the owner operations view —
     * status + reason codes + manual-suspension flag only). No platform detail,
     * no internal dimension payloads.
     *
     * @return array<string, mixed>
     */
    private function healthSummary(OwnerContext $context): array
    {
        $overview = $this->health->overview($context->tenant);

        return [
            'health_status' => $overview['health_status'] ?? null,
            'reason_codes' => $overview['reason_codes'] ?? [],
            'manual_suspension_active' => $overview['manual_suspension_active'] ?? false,
        ];
    }

    /**
     * @param  callable(): mixed  $read
     * @return array<string, mixed>
     */
    private function safe(callable $read): array
    {
        try {
            $value = $read();
        } catch (Throwable $e) {
            Log::warning('owner.support.panel_unavailable', ['exception' => $e::class]);

            return ['available' => false];
        }

        if (is_array($value)) {
            return ['available' => true] + $value;
        }

        return ['available' => true, 'value' => $value];
    }
}
