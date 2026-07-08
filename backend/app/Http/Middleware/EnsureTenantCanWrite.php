<?php

namespace App\Http\Middleware;

use App\Services\Entitlements\EntitlementAccessService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 32 — the runtime write gate driven by the tenant's billing/subscription/
 * lifecycle state (ENT-R011..R017). Only mutating verbs are gated; reads always
 * pass so existing data stays readable even when a tenant is read-only/over-quota
 * (ENT-R017).
 *
 * Runs AFTER subscription.active and tenant.lifecycle, so a suspended tenant is
 * already 423 TENANT_SUSPENDED and a subscription-blocked tenant already blocked
 * before this gate — this adds the Sprint 30 invoice (unpaid past grace) and
 * degraded-grace dimensions that those guards do not cover. Manual suspension is
 * still re-checked here so the decision is authoritative for the service layer
 * too. The denied/degraded decision is audit-logged inside EntitlementAccessService
 * (ENT-R018). Platform admins carry no tenant and pass through.
 *
 * Usage: ->middleware('entitlement.write')
 */
class EnsureTenantCanWrite
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        // Reads of existing data are never blocked by the write gate (ENT-R017).
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $decision = $this->access->canWrite($tenant, $this->context->user(), 'write');

        if ($decision->denied()) {
            return response()->json([
                'message' => $decision->message,
                'code' => $decision->reasonCode,
                'billing_state' => $decision->billingState,
            ], $this->statusFor($decision->reasonCode));
        }

        return $next($request);
    }

    private function statusFor(string $reasonCode): int
    {
        return match ($reasonCode) {
            'MANUALLY_SUSPENDED' => Response::HTTP_LOCKED,
            'UNPAID_PAST_GRACE', 'TRIAL_EXPIRED', 'MISSING_SUBSCRIPTION', 'SUBSCRIPTION_CANCELLED' => Response::HTTP_PAYMENT_REQUIRED,
            default => Response::HTTP_FORBIDDEN,
        };
    }
}
