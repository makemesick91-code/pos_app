#!/usr/bin/env bash
#
# Sprint 26 — Tenant Plan, Feature Entitlement & Usage Limit Governance
# Foundation smoke test. Structural validation only; does not build the Android
# app or run a database.
#
set -euo pipefail

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
SVC="$BACK/app/Services/TenantPlan"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MW="$BACK/app/Http/Middleware"
TP="docs/tenant-plan"

echo "== Migrations =="
check "tenant_plans migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_plans_table.php"
check "plan_entitlements migration exists" bash -c "ls $BACK/database/migrations/*create_plan_entitlements_table.php"
check "plan_usage_limits migration exists" bash -c "ls $BACK/database/migrations/*create_plan_usage_limits_table.php"
check "tenant_plan_assignments migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_plan_assignments_table.php"
check "tenant_entitlement_overrides migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_entitlement_overrides_table.php"

echo "== Models =="
for m in TenantPlan PlanEntitlement PlanUsageLimit TenantPlanAssignment TenantEntitlementOverride; do
  check "$m model" test -f "$BACK/app/Models/$m.php"
done

echo "== Services =="
for s in TenantPlanRegistrar TenantPlanResolver TenantPlanDecision FeatureEntitlementService TenantEntitlementGuard EntitlementDecision TenantUsageLimitService TenantUsageMeter UsageLimitDecision TenantPlanAssignmentService TenantEntitlementOverrideService TenantPlanSummaryService TenantPlanEnforcementAuditService TenantPlanReadinessService TenantPlanGoNoGoService SanitizesTenantPlanText; do
  check "$s" test -f "$SVC/$s.php"
done

echo "== Middleware / enforcement =="
check "EnsureTenantEntitled middleware" test -f "$MW/EnsureTenantEntitled.php"
check "EnsureTenantUsageLimitAvailable middleware" test -f "$MW/EnsureTenantUsageLimitAvailable.php"
check "tenant.entitled alias registered" hasf "tenant.entitled" "$BACK/bootstrap/app.php"
check "tenant.usage.limit alias registered" hasf "tenant.usage.limit" "$BACK/bootstrap/app.php"
check "entitled guard returns FEATURE_NOT_ENTITLED" hasf "FEATURE_NOT_ENTITLED" "$MW/EnsureTenantEntitled.php"
check "usage guard returns USAGE_LIMIT_EXCEEDED" hasf "USAGE_LIMIT_EXCEEDED" "$MW/EnsureTenantUsageLimitAvailable.php"
check "routes wire entitlement guard" hasf "tenant.entitled:" "$BACK/routes/api.php"
check "routes wire usage limit guard" hasf "tenant.usage.limit:" "$BACK/routes/api.php"
check "lifecycle still first on operational group" hasf "'subscription.active', 'tenant.lifecycle'" "$BACK/routes/api.php"

echo "== Controllers / requests / resources =="
for c in AdminTenantPlanController AdminTenantPlanAssignmentController AdminTenantEntitlementController AdminTenantUsageLimitController AdminTenantPlanGovernanceSummaryController; do
  check "$c" test -f "$ADMIN_CTRL/$c.php"
done
for r in StoreTenantPlanRequest UpdateTenantPlanRequest AssignTenantPlanRequest StoreTenantEntitlementOverrideRequest; do
  check "$r" test -f "$ADMIN_REQ/$r.php"
done
for r in TenantPlanResource TenantPlanAssignmentResource TenantEntitlementResource TenantUsageLimitResource TenantPlanGovernanceSummaryResource; do
  check "$r" test -f "$ADMIN_RES/$r.php"
done

echo "== Commands =="
for c in TenantPlanReadinessCommand TenantPlanEntitlementSummaryCommand TenantPlanUsageLimitSummaryCommand TenantPlanEnforcementAuditCommand TenantPlanGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done
check "readiness supports --json" hasf "json" "$CMD/TenantPlanReadinessCommand.php"
check "go-no-go supports --strict" hasf "strict" "$CMD/TenantPlanGoNoGoCommand.php"

echo "== Routes =="
check "routes register tenant-plans" hasf "tenant-plans" "$BACK/routes/api.php"
check "routes register tenant plan assign" hasf "tenants/{tenant}/plan" "$BACK/routes/api.php"
check "routes register entitlement overrides" hasf "tenants/{tenant}/entitlement-overrides" "$BACK/routes/api.php"
check "routes register usage-limits view" hasf "tenants/{tenant}/usage-limits" "$BACK/routes/api.php"
check "routes register governance summary" hasf "tenant-plan-governance/summary" "$BACK/routes/api.php"

echo "== Config / rules =="
check "tenant_plan config exists" test -f "$BACK/config/tenant_plan.php"
for r in TPE-R001 TPE-R002 TPE-R003 TPE-R004 TPE-R005 TPE-R006 TPE-R007 TPE-R008 TPE-R009 TPE-R010 TPE-R011 TPE-R012; do
  check "config locks $r" hasf "$r" "$BACK/config/tenant_plan.php"
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_26" hasf "sprint_26" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 26 runtime rule" hasf "Sprint 26 Tenant Plan, Feature Entitlement" docs/PROJECT_RULES.md
check "Sprint 25 TLS rules still present" hasf "TLS-R004" docs/PROJECT_RULES.md

echo "== Docs =="
check "architecture doc" test -f "docs/architecture/tenant-plan-entitlement-usage-governance.md"
check "plan-source-of-truth doc" test -f "$TP/plan-source-of-truth.md"
check "feature-entitlement-governance doc" test -f "$TP/feature-entitlement-governance.md"
check "usage-limit-governance doc" test -f "$TP/usage-limit-governance.md"
check "lifecycle-precedence doc" test -f "$TP/lifecycle-precedence.md"
check "sprint 26 doc" test -f "docs/sprints/sprint-26-tenant-plan-feature-entitlement-usage-limit-governance-foundation.md"

echo "== Tests =="
for t in TenantPlanResolutionTest TenantPlanEntitlementEnforcementTest TenantPlanUsageLimitEnforcementTest TenantPlanLifecyclePrecedenceTest TenantPlanAdminApiTest TenantPlanCommandsTest TenantPlanRulesLockTest; do
  check "$t exists" test -f "$BACK/tests/Feature/$t.php"
done

echo "== Android UX (not enforcement authority) =="
check "TenantPlanMessages exists" test -f "android/app/src/main/java/com/aishtech/poslite/core/network/TenantPlanMessages.kt"
check "Android plan access test exists" test -f "android/app/src/test/java/com/aishtech/poslite/TenantPlanAccessMessageTest.kt"
check "Android maps FEATURE_NOT_ENTITLED" hasf "FEATURE_NOT_ENTITLED" "android/app/src/main/java/com/aishtech/poslite/core/network/TenantPlanMessages.kt"
check "Android maps USAGE_LIMIT_EXCEEDED" hasf "USAGE_LIMIT_EXCEEDED" "android/app/src/main/java/com/aishtech/poslite/core/network/TenantPlanMessages.kt"

echo "== Security: no server-side plan enforcement in Android, no secrets =="
check "no server-side plan enforcement in Android" bash -c \
  '! grep -R "TenantPlanResolver\|FeatureEntitlementService\|TenantUsageLimitService\|tenant_plan_assignments\|EnsureTenantEntitled" android/app/src/main'
check "no payment/CRM keys in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|CRM_API_KEY\|WHATSAPP_TOKEN" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo "== Android package/SDK intact =="
check "Android package intact" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "Android minSdk 26 intact" hasf "minSdk = 26" android/app/build.gradle.kts
check "Android targetSdk 35 intact" hasf "targetSdk = 35" android/app/build.gradle.kts

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
