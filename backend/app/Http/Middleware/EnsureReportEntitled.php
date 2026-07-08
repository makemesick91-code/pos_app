<?php

namespace App\Http\Middleware;

use App\Services\Entitlements\EntitlementAccessService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 32 — enforces report entitlement at runtime (ENT-R010). A report is
 * denied when the tenant is suspended/unpaid-past-grace or the plan does not
 * grant the report's feature (REPORT_NOT_IN_PLAN). Every denial is audit-logged
 * inside EntitlementAccessService (ENT-R018). Platform admins pass through.
 *
 * Usage: ->middleware('entitlement.report:reports.advanced')
 */
class EnsureReportEntitled
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next, string $reportKey): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $decision = $this->access->canUseReport($tenant, $reportKey, $this->context->user());

        if ($decision->denied()) {
            return response()->json([
                'message' => $decision->message,
                'code' => $decision->reasonCode,
                'report' => $reportKey,
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
