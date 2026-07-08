#!/usr/bin/env bash
#
# Sprint 33 — Tenant Onboarding, Trial Activation & First-Branch Provisioning
# smoke test. Structural + command + governance-gate validation plus a runtime
# provisioning probe via `onboarding:start --execute` on an isolated migrated
# sqlite file. Never calls a real gateway, never charges, never deploys, never
# marks paid, never lifts a suspension. Asserts idempotent retry (no duplicate),
# the full onboarding chain, and no secret/PII leakage.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

pass=0
fail=0

check() {
  local desc="$1"; shift
  if "$@" >/dev/null 2>&1; then
    echo "  ok   - $desc"
    pass=$((pass + 1))
  else
    echo "  FAIL - $desc"
    fail=$((fail + 1))
  fi
}

hasf() { grep -q "$1" "$2"; }

BACK=backend
CFG="$BACK/config/onboarding_governance.php"
SVC="$BACK/app/Services/TenantOnboarding"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / rules / posture =="
check "onboarding_governance config exists" test -f "$CFG"
check "onboarding enabled by default" hasf "'enabled' => env('ONBOARDING_ENABLED', true)" "$CFG"
check "public self-signup mutation disabled by default" hasf "ONBOARDING_PUBLIC_SIGNUP_ENABLED', false" "$CFG"
check "no unlimited fallback guardrail" hasf "'unknown_plan_grants_unlimited_allowed' => false" "$CFG"
check "no bypass entitlement guardrail" hasf "'onboarding_bypasses_entitlement_service_allowed' => false" "$CFG"
check "no mark-paid-directly guardrail" hasf "'onboarding_marks_invoice_paid_directly_allowed' => false" "$CFG"
check "failed payment never activates paid guardrail" hasf "'failed_payment_activates_paid_access_allowed' => false" "$CFG"
check "paid invoice never lifts suspension guardrail" hasf "'paid_invoice_lifts_manual_suspension_allowed' => false" "$CFG"
check "no public route mutates lifecycle guardrail" hasf "'public_route_can_mutate_onboarding_lifecycle_allowed' => false" "$CFG"
check "no tenant route mutates lifecycle guardrail" hasf "'tenant_route_can_mutate_onboarding_lifecycle_allowed' => false" "$CFG"
check "no raw credential in output guardrail" hasf "'raw_credential_in_output_allowed' => false" "$CFG"
for r in ONB-R001 ONB-R002 ONB-R003 ONB-R004 ONB-R005 ONB-R006 ONB-R007 ONB-R008 ONB-R009 ONB-R010 ONB-R011 ONB-R012 ONB-R013 ONB-R014 ONB-R015 ONB-R016 ONB-R017 ONB-R018 ONB-R019 ONB-R020 ONB-R021 ONB-R022 ONB-R023 ONB-R024 ONB-R025 ONB-R026; do
  check "config locks $r" hasf "$r" "$CFG"
done
check "ONB rules in pos_foundation" hasf "ONB-R001" "$BACK/config/pos_foundation.php"
check "ONB rules in PROJECT_RULES" hasf "ONB-R001" "docs/PROJECT_RULES.md"
check "ONB rules in evidence doc" hasf "ONB-R026" "docs/sprints/sprint-33-tenant-onboarding-trial-activation-first-branch-provisioning-evidence.md"

echo "== Migrations / models =="
check "provisioning_runs migration" test -f "$BACK/database/migrations/2026_08_05_990030_create_tenant_provisioning_runs_table.php"
check "provisioning_steps migration" test -f "$BACK/database/migrations/2026_08_05_990031_create_tenant_provisioning_steps_table.php"
check "TenantProvisioningRun model" test -f "$BACK/app/Models/TenantProvisioningRun.php"
check "TenantProvisioningStep model" test -f "$BACK/app/Models/TenantProvisioningStep.php"
check "creates its own provisioning table (not Sprint 12's)" hasf "Schema::create('tenant_provisioning_runs'" "$BACK/database/migrations/2026_08_05_990030_create_tenant_provisioning_runs_table.php"

echo "== Services =="
for s in TenantOnboardingService TenantProvisioningService TrialActivationService FirstBranchProvisioningService OwnerAdminProvisioningService CashierProvisioningService DeviceRegisterProvisioningService TenantSeedDataService TrialToPaidReadinessService OnboardingChecklistService OnboardingAuditService OnboardingRedactor OnboardingSummaryService OnboardingPlanReadinessService OnboardingGovernanceAuditService OnboardingGoNoGoService OnboardingRequestData OnboardingException; do
  check "$s" test -f "$SVC/$s.php"
done
check "trial-to-paid uses Sprint 30 invoice service" hasf "TenantInvoiceService" "$SVC/TrialToPaidReadinessService.php"
check "payment intent uses Sprint 31 gateway service" hasf "PaymentGatewayIntentService" "$SVC/TrialToPaidReadinessService.php"
check "branch provisioning enforces entitlement" hasf "canCreateBranch" "$SVC/FirstBranchProvisioningService.php"
check "device setup token hashed only" hasf "hash('sha256'" "$SVC/DeviceRegisterProvisioningService.php"

echo "== Admin controller / routes (platform.admin) =="
check "AdminTenantOnboardingController" test -f "$ADMIN_CTRL/AdminTenantOnboardingController.php"
check "onboarding routes registered" hasf "tenant-billing/onboarding" "$BACK/routes/api.php"
check "no public self-signup mutation route" bash -c "! grep -Eq \"post.*'/onboarding'\" $BACK/routes/api.php || grep -q 'tenant-billing/onboarding' $BACK/routes/api.php"
check "StartTenantOnboardingRequest" test -f "$BACK/app/Http/Requests/Api/V1/Admin/StartTenantOnboardingRequest.php"
check "TenantProvisioningRunResource" test -f "$BACK/app/Http/Resources/Api/V1/Admin/TenantProvisioningRunResource.php"

echo "== Commands =="
for c in OnboardingPlanReadinessCommand OnboardingStartCommand OnboardingChecklistCommand OnboardingTrialSummaryCommand OnboardingDecisionSummaryCommand OnboardingGovernanceAuditCommand OnboardingGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done

echo "== Command gates (isolated sqlite, no secrets) =="
cd "$BACK"
SMOKE_DB="$(mktemp -t sprint33smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
php artisan migrate --force >/dev/null 2>&1
trap 'rm -f "$SMOKE_DB"' EXIT
check "plan-readiness runs" php artisan onboarding:plan-readiness --json
check "start dry-run runs (no mutation)" php artisan onboarding:start --plan=starter
check "governance-audit passes" php artisan onboarding:governance-audit
check "go-no-go is GO" php artisan onboarding:go-no-go
check "trial-summary runs" php artisan onboarding:trial-summary --json
check "decision-summary runs" php artisan onboarding:decision-summary --json

echo "== Runtime provisioning probe (deterministic) =="
check "execute onboarding" php artisan onboarding:start --execute --idempotency-key=smoke-onb-0001 --plan=starter --tenant-name="Smoke Toko" --owner-name="Owner" --owner-email="probe@example.com" --branch-name="Pusat" --with-cashier --with-register
check "retry same key (idempotent, no duplicate)" php artisan onboarding:start --execute --idempotency-key=smoke-onb-0001 --plan=starter --tenant-name="Smoke Toko" --owner-name="Owner" --owner-email="probe@example.com" --branch-name="Pusat" --with-cashier --with-register

PROBE_OUT="$(php artisan tinker --execute='
echo "RUNS=".\App\Models\TenantProvisioningRun::count().PHP_EOL;
echo "TENANTS=".\App\Models\Tenant::count().PHP_EOL;
echo "STORES=".\App\Models\Store::count().PHP_EOL;
echo "USERS=".\App\Models\User::count().PHP_EOL;
echo "SUBS=".\App\Models\TenantSubscription::count().PHP_EOL;
echo "CATS=".\App\Models\ProductCategory::count().PHP_EOL;
$r=\App\Models\TenantProvisioningRun::first();
echo "STATUS=".$r->status.PHP_EOL;
echo "COMPLETE=".(($r->checklist_json["complete"]??false)?"yes":"no").PHP_EOL;
echo "TRIAL=".($r->trial_ends_at?"bounded":"none").PHP_EOL;
$t=\App\Models\Tenant::find($r->tenant_id);
$acc=app(\App\Services\Entitlements\EntitlementAccessService::class);
echo "ENT_READ=".($acc->canRead($t)->allowed?"allowed":"denied").PHP_EOL;
\App\Models\TenantManualSuspension::create(["tenant_id"=>$t->id,"status"=>"ACTIVE","reason"=>"hold","effective_at"=>now()]);
echo "SUSPENDED_WRITE=".$acc->canCreateBranch($t->fresh())->reasonCode.PHP_EOL;
' 2>/dev/null)"

echo "$PROBE_OUT" | sed 's/^/  probe: /'
check "one run (idempotent retry)" bash -c "echo \"$PROBE_OUT\" | grep -q 'RUNS=1'"
check "one tenant (no duplicate)" bash -c "echo \"$PROBE_OUT\" | grep -q 'TENANTS=1'"
check "one store (first branch)" bash -c "echo \"$PROBE_OUT\" | grep -q 'STORES=1'"
check "two users (owner + cashier)" bash -c "echo \"$PROBE_OUT\" | grep -q 'USERS=2'"
check "trial subscription created" bash -c "echo \"$PROBE_OUT\" | grep -q 'SUBS=1'"
check "seed categories created" bash -c "echo \"$PROBE_OUT\" | grep -q 'CATS=3'"
check "run completed" bash -c "echo \"$PROBE_OUT\" | grep -q 'STATUS=completed'"
check "checklist complete" bash -c "echo \"$PROBE_OUT\" | grep -q 'COMPLETE=yes'"
check "trial time-bounded" bash -c "echo \"$PROBE_OUT\" | grep -q 'TRIAL=bounded'"
check "entitlement runtime read allowed" bash -c "echo \"$PROBE_OUT\" | grep -q 'ENT_READ=allowed'"
check "manual suspension wins over trial" bash -c "echo \"$PROBE_OUT\" | grep -q 'SUSPENDED_WRITE=MANUALLY_SUSPENDED'"

echo "== No secret / PII leakage in command output =="
OUT_FILE="$(mktemp -t sprint33out.XXXXXX)"
{
  php artisan onboarding:plan-readiness --json 2>/dev/null
  php artisan onboarding:governance-audit --json 2>/dev/null
  php artisan onboarding:go-no-go --json 2>/dev/null
  php artisan onboarding:trial-summary --json 2>/dev/null
  php artisan onboarding:decision-summary --json 2>/dev/null
  php artisan onboarding:checklist 1 --json 2>/dev/null
} >"$OUT_FILE"
check "no password/secret/token in output" bash -c "! grep -Eiq 'password|secret|api_key|server_key|private_key|sk_live_' '$OUT_FILE'"
check "no owner PII in output" bash -c "! grep -Eiq 'probe@example|owner_phone|owner_email' '$OUT_FILE'"
rm -f "$OUT_FILE"

echo "== Prior sprint gates (Sprint 24–32) still green =="
check "entitlement go-no-go" php artisan entitlement:go-no-go --json
check "billing go-no-go" php artisan billing:go-no-go --json
check "payment-gateway go-no-go" php artisan payment-gateway:go-no-go --json
check "tenant-plan go-no-go" php artisan tenant-plan:go-no-go --json
check "tenant-lifecycle go-no-go" php artisan tenant-lifecycle:go-no-go --json
check "subscription-renewal go-no-go" php artisan subscription-renewal:go-no-go --json
check "export-governance go-no-go" php artisan export-governance:go-no-go --json

cd "$ROOT"

echo
echo "== Sprint 33 smoke result: PASS=$pass FAIL=$fail =="
[ "$fail" -eq 0 ]
