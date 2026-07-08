#!/usr/bin/env bash
#
# Sprint 25 — Tenant Lifecycle Enforcement & Manual Suspension Governance
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
SVC="$BACK/app/Services/TenantLifecycle"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MW="$BACK/app/Http/Middleware"
TL="docs/tenant-lifecycle"

echo "== Migrations =="
check "manual suspensions migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_manual_suspensions_table.php"
check "lifecycle events migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_lifecycle_events_table.php"

echo "== Models =="
check "TenantManualSuspension model" test -f "$BACK/app/Models/TenantManualSuspension.php"
check "TenantLifecycleEvent model" test -f "$BACK/app/Models/TenantLifecycleEvent.php"

echo "== Services =="
check "TenantLifecycleStatus" test -f "$SVC/TenantLifecycleStatus.php"
check "TenantLifecycleDecision" test -f "$SVC/TenantLifecycleDecision.php"
check "TenantLifecycleService" test -f "$SVC/TenantLifecycleService.php"
check "TenantLifecycleAccessGuard" test -f "$SVC/TenantLifecycleAccessGuard.php"
check "TenantSuspensionService" test -f "$SVC/TenantSuspensionService.php"
check "TenantSuspensionSummaryService" test -f "$SVC/TenantSuspensionSummaryService.php"
check "TenantLifecycleEnforcementAuditService" test -f "$SVC/TenantLifecycleEnforcementAuditService.php"
check "TenantLifecycleReadinessService" test -f "$SVC/TenantLifecycleReadinessService.php"
check "TenantLifecycleGoNoGoService" test -f "$SVC/TenantLifecycleGoNoGoService.php"
check "SanitizesTenantLifecycleText" test -f "$SVC/SanitizesTenantLifecycleText.php"

echo "== Middleware / enforcement =="
check "EnsureTenantLifecycleAllowed middleware" test -f "$MW/EnsureTenantLifecycleAllowed.php"
check "tenant.lifecycle alias registered" hasf "tenant.lifecycle" "$BACK/bootstrap/app.php"
check "operational group uses tenant.lifecycle" hasf "'subscription.active', 'tenant.lifecycle'" "$BACK/routes/api.php"
check "guard returns TENANT_SUSPENDED" hasf "TENANT_SUSPENDED" "$MW/EnsureTenantLifecycleAllowed.php"
check "guard returns 423 Locked" hasf "HTTP_LOCKED" "$MW/EnsureTenantLifecycleAllowed.php"

echo "== Controllers / requests / resources =="
check "AdminTenantLifecycleController" test -f "$ADMIN_CTRL/AdminTenantLifecycleController.php"
check "AdminTenantSuspensionController" test -f "$ADMIN_CTRL/AdminTenantSuspensionController.php"
check "AdminTenantLifecycleSuspensionSummaryController" test -f "$ADMIN_CTRL/AdminTenantLifecycleSuspensionSummaryController.php"
check "SuspendTenantRequest" test -f "$ADMIN_REQ/SuspendTenantRequest.php"
check "LiftTenantSuspensionRequest" test -f "$ADMIN_REQ/LiftTenantSuspensionRequest.php"
check "TenantLifecycleResource" test -f "$ADMIN_RES/TenantLifecycleResource.php"
check "TenantManualSuspensionResource" test -f "$ADMIN_RES/TenantManualSuspensionResource.php"
check "TenantSuspensionSummaryResource" test -f "$ADMIN_RES/TenantSuspensionSummaryResource.php"

echo "== Commands =="
check "readiness command" test -f "$CMD/TenantLifecycleReadinessCommand.php"
check "suspension-summary command" test -f "$CMD/TenantLifecycleSuspensionSummaryCommand.php"
check "enforcement-audit command" test -f "$CMD/TenantLifecycleEnforcementAuditCommand.php"
check "go-no-go command" test -f "$CMD/TenantLifecycleGoNoGoCommand.php"
check "readiness supports --json" hasf "json" "$CMD/TenantLifecycleReadinessCommand.php"
check "go-no-go supports --strict" hasf "strict" "$CMD/TenantLifecycleGoNoGoCommand.php"

echo "== Routes =="
check "routes register lifecycle view" hasf "tenants/{tenant}/lifecycle" "$BACK/routes/api.php"
check "routes register suspend" hasf "tenants/{tenant}/suspend" "$BACK/routes/api.php"
check "routes register lift-suspension" hasf "tenants/{tenant}/lift-suspension" "$BACK/routes/api.php"
check "routes register suspension-summary" hasf "tenant-lifecycle/suspension-summary" "$BACK/routes/api.php"

echo "== Config / rules =="
check "tenant_lifecycle config exists" test -f "$BACK/config/tenant_lifecycle.php"
for r in TLS-R001 TLS-R002 TLS-R003 TLS-R004 TLS-R005 TLS-R006 TLS-R007 TLS-R008 TLS-R009 TLS-R010; do
  check "config locks $r" hasf "$r" "$BACK/config/tenant_lifecycle.php"
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_25" hasf "sprint_25" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 25 runtime rule" hasf "Sprint 25 Tenant Lifecycle Enforcement" docs/PROJECT_RULES.md

echo "== Docs =="
check "tenant-lifecycle-governance doc" test -f "docs/architecture/tenant-lifecycle-governance.md"
check "status-model doc" test -f "$TL/tenant-lifecycle-status-model.md"
check "manual-suspension-governance doc" test -f "$TL/manual-suspension-governance.md"
check "enforcement-allowlist doc" test -f "$TL/enforcement-allowlist.md"
check "renewal-dunning-precedence doc" test -f "$TL/renewal-dunning-precedence.md"
check "go-watch-no-go-report doc" test -f "$TL/tenant-lifecycle-go-watch-no-go-report.md"
check "sprint 25 doc" test -f "docs/sprints/sprint-25-tenant-lifecycle-enforcement-manual-suspension-governance-foundation.md"

echo "== Tests =="
for t in TenantManualSuspensionAdminApiTest TenantLifecycleEnforcementTest TenantLifecyclePrecedenceTest TenantLifecycleCommandsTest TenantLifecycleServiceTest TenantLifecycleRulesLockTest; do
  check "$t exists" test -f "$BACK/tests/Feature/$t.php"
done

echo "== Android UX (not enforcement authority) =="
check "TenantAccessMessages exists" test -f "android/app/src/main/java/com/aishtech/poslite/core/network/TenantAccessMessages.kt"
check "Android suspension test exists" test -f "android/app/src/test/java/com/aishtech/poslite/TenantSuspensionMessageTest.kt"
check "Android maps TENANT_SUSPENDED" hasf "TENANT_SUSPENDED" "android/app/src/main/java/com/aishtech/poslite/core/network/TenantAccessMessages.kt"

echo "== Security: no tenant suspension enforcement in Android, no secrets =="
check "no server-side suspension enforcement in Android" bash -c \
  '! grep -R "TenantSuspensionService\|tenant_manual_suspensions\|EnsureTenantLifecycleAllowed" android/app/src/main'
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
