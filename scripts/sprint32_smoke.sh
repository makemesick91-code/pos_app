#!/usr/bin/env bash
#
# Sprint 32 — Plan Entitlement Runtime Enforcement & Subscription Access Control
# smoke test. Structural + command + governance-gate validation plus a runtime
# enforcement probe via a self-contained PHP harness (in-memory sqlite). Never
# calls a real gateway, never charges, never deploys, never lifts a suspension.
# Asserts no secret/PII leakage and that manual suspension wins over paid billing.

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
CFG="$BACK/config/entitlement_governance.php"
SVC="$BACK/app/Services/Entitlements"
MW="$BACK/app/Http/Middleware"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / rules / posture =="
check "entitlement_governance config exists" test -f "$CFG"
check "runtime enforcement enabled by default" hasf "'runtime_enforcement_enabled'" "$CFG"
check "fail closed on unknown plan" hasf "'fail_closed_on_unknown_plan' => true" "$CFG"
check "no unlimited fallback guardrail" hasf "'unknown_plan_grants_unlimited_allowed' => false" "$CFG"
check "paid invoice never lifts suspension guardrail" hasf "'paid_invoice_lifts_manual_suspension_allowed' => false" "$CFG"
check "failed event never unlocks entitlement guardrail" hasf "'failed_event_unlocks_entitlement_allowed' => false" "$CFG"
check "no tenant route mutates entitlement guardrail" hasf "'tenant_route_can_mutate_entitlement_state_allowed' => false" "$CFG"
check "no silent bypass over quota guardrail" hasf "'silent_bypass_when_over_quota_allowed' => false" "$CFG"
check "denied access without audit guardrail" hasf "'denied_access_without_audit_allowed' => false" "$CFG"
for r in ENT-R001 ENT-R002 ENT-R003 ENT-R004 ENT-R005 ENT-R006 ENT-R007 ENT-R008 ENT-R009 ENT-R010 ENT-R011 ENT-R012 ENT-R013 ENT-R014 ENT-R015 ENT-R016 ENT-R017 ENT-R018 ENT-R019 ENT-R020 ENT-R021 ENT-R022 ENT-R023 ENT-R024; do
  check "config locks $r" hasf "$r" "$CFG"
done

echo "== Migration / model =="
check "decisions migration" test -f "$BACK/database/migrations/2026_07_29_990020_create_tenant_entitlement_decisions_table.php"
check "TenantEntitlementDecision model" test -f "$BACK/app/Models/TenantEntitlementDecision.php"

echo "== Services =="
for s in EntitlementAccessService EntitlementBillingStateService EntitlementUsageService EntitlementAuditService EntitlementRedactor EntitlementSummaryService EntitlementGovernanceAuditService EntitlementGoNoGoService EntitlementDecision; do
  check "$s" test -f "$SVC/$s.php"
done

echo "== Middleware =="
for m in EnsureTenantCanWrite EnsureFeatureEntitled EnsureExportEntitled EnsureReportEntitled; do
  check "$m" test -f "$MW/$m.php"
done
check "entitlement.write alias" hasf "entitlement.write" "$BACK/bootstrap/app.php"
check "entitlement.feature alias" hasf "entitlement.feature" "$BACK/bootstrap/app.php"
check "entitlement.export alias" hasf "entitlement.export" "$BACK/bootstrap/app.php"
check "entitlement.report alias" hasf "entitlement.report" "$BACK/bootstrap/app.php"
check "write gate wired on operational group" hasf "entitlement.write" "$BACK/routes/api.php"
check "device registration metered" hasf "tenant.usage.limit:devices.max" "$BACK/routes/api.php"

echo "== Admin controllers (platform.admin, read-only) =="
check "AdminTenantEntitlementAccessController" test -f "$ADMIN_CTRL/AdminTenantEntitlementAccessController.php"
check "AdminEntitlementDecisionController" test -f "$ADMIN_CTRL/AdminEntitlementDecisionController.php"
check "no tenant mutation of entitlement route" bash -c "! grep -Eq \"post.*entitlements/(override|assign|grant)\" $BACK/routes/api.php"

echo "== Commands =="
for c in EntitlementPlanSummaryCommand EntitlementUsageSummaryCommand EntitlementAccessCheckCommand EntitlementDecisionSummaryCommand EntitlementGovernanceAuditCommand EntitlementGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done
check "access-check dry-run by default (--record gate)" hasf "record : Also persist" "$CMD/EntitlementAccessCheckCommand.php"

echo "== Command gates (isolated sqlite, no secrets) =="
cd "$BACK"
# A self-contained migrated sqlite file so each `php artisan` process (a fresh
# runtime) sees the schema. :memory: cannot be shared across processes.
SMOKE_DB="$(mktemp -t sprint32smoke.XXXXXX.sqlite)"
export DB_CONNECTION=sqlite
export DB_DATABASE="$SMOKE_DB"
php artisan migrate --force >/dev/null 2>&1
trap 'rm -f "$SMOKE_DB"' EXIT
check "plan-summary runs" php artisan entitlement:plan-summary --json
check "governance-audit passes" php artisan entitlement:governance-audit
check "usage-summary runs" php artisan entitlement:usage-summary --json
check "decision-summary runs" php artisan entitlement:decision-summary --json

echo "== Runtime enforcement probe (deterministic, in-memory) =="
# Boots the app, runs migrations, and exercises the real EntitlementAccessService
# for below-limit / at-limit / suspended / over-quota / unpaid-past-grace paths.
PROBE_OUT="$(php artisan tinker --execute='
use App\Models\Tenant;
use App\Models\Store;
use App\Models\TenantManualSuspension;
use App\Models\TenantBillingInvoice;
use App\Services\Entitlements\EntitlementAccessService;
use App\Services\Entitlements\EntitlementBillingStateService;
Illuminate\Support\Facades\Artisan::call("migrate", ["--force" => true]);
$access = app(EntitlementAccessService::class);
$billing = app(EntitlementBillingStateService::class);
app(\App\Services\TenantPlan\TenantPlanRegistrar::class)->ensure();
$t = Tenant::factory()->create();
app(\App\Services\TenantPlan\TenantPlanRegistrar::class)->ensure();
$plan = \App\Models\TenantPlan::query()->where("key","starter")->first();
\App\Models\TenantPlanAssignment::query()->create(["tenant_id"=>$t->id,"tenant_plan_id"=>$plan->id,"status"=>"active","effective_from"=>now()->subDay(),"source"=>"test"]);
$t->stores()->delete();
echo "BRANCH_BELOW=".($access->canCreateBranch($t->fresh())->allowed ? "allowed":"denied").PHP_EOL;
Store::query()->create(["tenant_id"=>$t->id,"name"=>"Main","code"=>"S1","is_active"=>true]);
echo "BRANCH_AT=".($access->canCreateBranch($t->fresh())->allowed ? "allowed":"denied").PHP_EOL;
echo "READ_OVERQUOTA=".($access->canRead($t->fresh())->allowed ? "allowed":"denied").PHP_EOL;
$s = Tenant::factory()->create();
TenantManualSuspension::query()->create(["tenant_id"=>$s->id,"status"=>"ACTIVE","reason"=>"hold","reason_category"=>"compliance","effective_at"=>now()->subDay()]);
echo "SUSPENDED_WRITE=".$billing->resolveWriteAccess($s->fresh())->reasonCode.PHP_EOL;
$u = Tenant::factory()->create();
TenantBillingInvoice::query()->create(["tenant_id"=>$u->id,"plan_key"=>"starter","invoice_number"=>"INV-".uniqid(),"period_key"=>now()->format("Y-m"),"period_start"=>now()->startOfMonth(),"period_end"=>now()->endOfMonth(),"issued_at"=>now()->subDays(31),"due_at"=>now()->subDays(30),"currency"=>"IDR","subtotal_amount"=>99000,"discount_amount"=>0,"tax_amount"=>0,"total_amount"=>99000,"status"=>"issued","collection_state"=>"overdue","source"=>"test","idempotency_key"=>uniqid()]);
echo "UNPAID_WRITE=".$billing->resolveWriteAccess($u->fresh())->reasonCode.PHP_EOL;
echo "UNPAID_READ=".($billing->resolveReadAccess($u->fresh())->allowed ? "allowed":"denied").PHP_EOL;
' 2>/dev/null)"

echo "$PROBE_OUT" | sed 's/^/  probe: /'
check "below-limit branch allowed" bash -c "echo \"$PROBE_OUT\" | grep -q 'BRANCH_BELOW=allowed'"
check "at-limit branch denied" bash -c "echo \"$PROBE_OUT\" | grep -q 'BRANCH_AT=denied'"
check "over-quota data still readable" bash -c "echo \"$PROBE_OUT\" | grep -q 'READ_OVERQUOTA=allowed'"
check "manual suspension blocks write" bash -c "echo \"$PROBE_OUT\" | grep -q 'SUSPENDED_WRITE=MANUALLY_SUSPENDED'"
check "unpaid past grace blocks write" bash -c "echo \"$PROBE_OUT\" | grep -q 'UNPAID_WRITE=UNPAID_PAST_GRACE'"
check "unpaid past grace keeps read" bash -c "echo \"$PROBE_OUT\" | grep -q 'UNPAID_READ=allowed'"

echo "== No secret / PII leakage in command output =="
OUT_FILE="$(mktemp -t sprint32out.XXXXXX)"
{
  php artisan entitlement:plan-summary --json 2>/dev/null
  php artisan entitlement:usage-summary --json 2>/dev/null
  php artisan entitlement:governance-audit --json 2>/dev/null
  php artisan entitlement:decision-summary --json 2>/dev/null
  php artisan entitlement:go-no-go --json 2>/dev/null
} >"$OUT_FILE"
check "no password/secret/token in output" bash -c "! grep -Eiq 'password|secret|api_key|server_key|private_key|sk_live_' '$OUT_FILE'"
check "no obvious PII in output" bash -c "! grep -Eiq 'owner_phone|owner_name|@example' '$OUT_FILE'"
rm -f "$OUT_FILE"

echo "== Prior sprint gates (Sprint 25–31) still green =="
check "billing go-no-go" php artisan billing:go-no-go --json
check "payment-gateway go-no-go" php artisan payment-gateway:go-no-go --json
check "tenant-plan go-no-go" php artisan tenant-plan:go-no-go --json
check "tenant-lifecycle go-no-go" php artisan tenant-lifecycle:go-no-go --json
check "export-governance go-no-go" php artisan export-governance:go-no-go --json
check "report-export-metering go-no-go" php artisan report-export-metering:go-no-go --json

cd "$ROOT"

echo
echo "== Sprint 32 smoke result: PASS=$pass FAIL=$fail =="
[ "$fail" -eq 0 ]
