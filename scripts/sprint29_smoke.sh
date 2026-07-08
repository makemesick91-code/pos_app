#!/usr/bin/env bash
#
# Sprint 29 — Multi-Export Route Metering Coverage & Export Governance Expansions
# smoke test. Structural + command validation; does not build the Android app.
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
SVC="$BACK/app/Services/ExportGovernance"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / registry =="
check "export_governance config exists" test -f "$BACK/config/export_governance.php"
check "canonical meter key reports.exports.monthly" hasf "'meter_key' => 'reports.exports.monthly'" "$BACK/config/export_governance.php"
check "canonical event key report.exported" hasf "'event_key' => 'report.exported'" "$BACK/config/export_governance.php"
check "daily-sales CSV export is metered" hasf "GET api/v1/reports/daily-sales/export.csv" "$BACK/config/export_governance.php"
check "registry declares an exempt reason" hasf "exempt_reason" "$BACK/config/export_governance.php"

echo "== Services =="
for s in ExportRouteRegistry ExportRouteDiscoveryService ExportGovernanceAuditService ExportGovernanceCoverageService ExportGovernanceGoNoGoService; do
  check "$s" test -f "$SVC/$s.php"
done
check "discovery scans the live route table" hasf "getRoutes" "$SVC/ExportRouteDiscoveryService.php"
check "audit checks lifecycle→entitlement→usage order" hasf "entitlement before lifecycle" "$SVC/ExportGovernanceAuditService.php"

echo "== Commands =="
for c in ExportGovernanceRouteScanCommand ExportGovernanceCoverageSummaryCommand ExportGovernanceMeteringAuditCommand ExportGovernanceGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done

echo "== Admin (read-only) =="
check "AdminExportGovernanceController" test -f "$ADMIN_CTRL/AdminExportGovernanceController.php"
check "routes register export-governance/routes" hasf "export-governance/routes" "$BACK/routes/api.php"
check "routes register export-governance/coverage-summary" hasf "export-governance/coverage-summary" "$BACK/routes/api.php"
check "routes register export-governance/metering-summary" hasf "export-governance/metering-summary" "$BACK/routes/api.php"

echo "== Rules / foundation =="
for r in EGC-R001 EGC-R002 EGC-R003 EGC-R004 EGC-R005 EGC-R006 EGC-R007 EGC-R008 EGC-R009 EGC-R010 EGC-R011 EGC-R012 EGC-R013 EGC-R014 EGC-R015; do
  check "config locks $r" hasf "$r" "$BACK/config/export_governance.php"
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_29" hasf "sprint_29" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 29 runtime rule" hasf "Sprint 29 Multi-Export Route Metering Coverage" docs/PROJECT_RULES.md
check "Sprint 28 ULR rules still present" hasf "ULR-R016" docs/PROJECT_RULES.md
check "Sprint 27 UEL rules still present" hasf "UEL-R015" docs/PROJECT_RULES.md
check "Sprint 26 TPE rules still present" hasf "TPE-R012" docs/PROJECT_RULES.md
check "Sprint 25 TLS rules still present" hasf "TLS-R004" docs/PROJECT_RULES.md

echo "== CI grep rules =="
check "EGC-R003 grep target present" bash -c "grep -R 'EGC-R003' $BACK/config docs >/dev/null"
check "EGC-R008 grep target present" bash -c "grep -R 'EGC-R008' $BACK/config docs >/dev/null"
check "EGC-R015 grep target present" bash -c "grep -R 'EGC-R015' $BACK/config docs >/dev/null"

echo "== Docs =="
check "architecture doc" test -f "docs/architecture/multi-export-route-metering-export-governance.md"
check "sprint 29 doc" test -f "docs/sprints/sprint-29-multi-export-route-metering-coverage-export-governance-expansions.md"

echo "== Tests present =="
for t in ExportGovernanceRegistryTest ExportGovernanceRuntimeTest ExportGovernanceAdminApiTest ExportGovernanceCommandsTest ExportGovernanceRulesLockTest; do
  check "$t exists" test -f "$BACK/tests/Feature/$t.php"
done

echo "== Security: no export metering bypass / ledger mutation route in runtime =="
check "no mutation route on export-governance" bash -c \
  "! grep -E 'Route::(post|put|patch|delete)[^;]*export-governance' $BACK/routes/api.php"
check "no runtime mutation route for usage ledger" bash -c \
  "! grep -E 'Route::(post|put|patch|delete)[^;]*(usage-events|usage-ledger)' $BACK/routes/api.php"
check "no server-side export governance in Android" bash -c \
  "! grep -R 'export_governance\|ExportGovernance' android/app/src/main 2>/dev/null"

echo "== Commands run clean (Sprint 29 gate + prior gates) =="
SMOKE_DB="$(mktemp -t sprint29_smoke_XXXXXX.sqlite)"
trap 'rm -f "$SMOKE_DB"' EXIT
( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan migrate --force ) >/dev/null 2>&1
run_art() { ( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan "$@" ); }
check "export-governance:route-scan --strict" run_art export-governance:route-scan --strict --json
check "export-governance:coverage-summary" run_art export-governance:coverage-summary --json
check "export-governance:metering-audit" run_art export-governance:metering-audit --json
check "export-governance:go-no-go --strict" run_art export-governance:go-no-go --strict --json
check "usage-ledger:go-no-go --strict (Sprint 28)" run_art usage-ledger:go-no-go --strict --json
check "report-export-metering:go-no-go (Sprint 27)" run_art report-export-metering:go-no-go --json
check "tenant-plan:go-no-go (Sprint 26)" run_art tenant-plan:go-no-go --json
check "tenant-lifecycle:go-no-go (Sprint 25)" run_art tenant-lifecycle:go-no-go --json

echo "== reports.exports.monthly meterable true =="
check "meter remains meterable" hasf "'reports.exports.monthly' => \['label' => 'Report exports per month', 'period' => 'monthly', 'meterable' => true\]" "$BACK/config/tenant_plan.php"

echo "== No secrets leaked in command output =="
no_secret_markers() {
  ! ( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan export-governance:coverage-summary --json 2>&1 ) \
    | grep -Eiq 'sk_live|password=|secret=|bearer '
}
check "coverage-summary output has no secret markers" no_secret_markers

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Sprint 29 smoke: $fail failures  (passed: $pass)"
[ "$fail" -eq 0 ]
