<?php

namespace App\Http\Middleware;

use App\Services\Entitlements\EntitlementAccessService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 32 — enforces export entitlement at runtime (ENT-R010). An export is
 * denied when the tenant is suspended/unpaid-past-grace, when the plan does not
 * grant the export's feature (EXPORT_NOT_IN_PLAN), or when the export usage limit
 * is exceeded (USAGE_LIMIT_EXCEEDED). Every denial is audit-logged inside
 * EntitlementAccessService (ENT-R018). Integrates with the Sprint 27–29 export
 * metering rather than replacing it.
 *
 * Usage: ->middleware('entitlement.export:reports.daily-sales.csv')
 */
class EnsureExportEntitled
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntitlementAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next, string $exportKey): Response
    {
        $tenant = $this->context->tenant();

        if ($tenant === null) {
            return $next($request);
        }

        $decision = $this->access->canUseExport($tenant, $exportKey, $this->context->user());

        if ($decision->denied()) {
            return response()->json([
                'message' => $decision->message,
                'code' => $decision->reasonCode,
                'export' => $exportKey,
            ], $this->statusFor($decision->reasonCode));
        }

        return $next($request);
    }

    private function statusFor(string $reasonCode): int
    {
        return match ($reasonCode) {
            'MANUALLY_SUSPENDED' => Response::HTTP_LOCKED,
            'USAGE_LIMIT_EXCEEDED' => Response::HTTP_TOO_MANY_REQUESTS,
            'UNPAID_PAST_GRACE', 'TRIAL_EXPIRED', 'MISSING_SUBSCRIPTION', 'SUBSCRIPTION_CANCELLED' => Response::HTTP_PAYMENT_REQUIRED,
            default => Response::HTTP_FORBIDDEN,
        };
    }
}
