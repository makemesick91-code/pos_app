#!/usr/bin/env bash
#
# Sprint 28 — Usage Ledger Anomaly Detection & Governed Repair Foundation smoke
# test. Structural + command validation; does not build the Android app.
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
SVC="$BACK/app/Services/UsageLedgerAnomaly"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Migration / model =="
check "tenant_usage_ledger_repairs migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_usage_ledger_repairs_table.php"
check "TenantUsageLedgerRepair model" test -f "$BACK/app/Models/TenantUsageLedgerRepair.php"
check "repair record is idempotent (unique repair_key)" hasf "unique(\['tenant_id', 'repair_key'\])" "$BACK"/database/migrations/*create_tenant_usage_ledger_repairs_table.php
check "repair delta is signed integer" hasf "integer('quantity_delta')" "$BACK"/database/migrations/*create_tenant_usage_ledger_repairs_table.php

echo "== Anomaly detector services =="
for s in UsageLedgerAnomaly UsageLedgerAnomalySeverity UsageLedgerAnomalyRepository UsageLedgerAnomalyDetector UsageLedgerAnomalySummary; do
  check "$s" test -f "$SVC/$s.php"
done
check "detector reads canonical ledger" hasf "UsageLedgerAnomalyRepository" "$SVC/UsageLedgerAnomalyDetector.php"
check "detector redacts secret metadata keys" hasf "offendingMetadataKeys" "$SVC/UsageLedgerAnomalyDetector.php"

echo "== Governed repair services =="
for s in UsageLedgerRepairPlanner UsageLedgerRepairService UsageLedgerRepairDecision UsageLedgerRepairAuditPayload UsageLedgerRepairSummaryService UsageLedgerGoNoGoService; do
  check "$s" test -f "$SVC/$s.php"
done
check "repair service appends correction (no update/delete)" hasf "TenantUsageLedgerRepair::query()->create" "$SVC/UsageLedgerRepairService.php"
check "repair service clamps effective usage >= 0" hasf 'max($decision->quantityDelta, -$base)' "$SVC/UsageLedgerRepairService.php"
check "meter derives effective usage from ledger + repairs" hasf "repairDelta" "$BACK/app/Services/UsageEventLedger/UsageEventLedgerService.php"

echo "== Meter wiring (Sprint 27 preserved) =="
check "reports.exports.monthly is meterable true" hasf "'reports.exports.monthly' => \['label' => 'Report exports per month', 'period' => 'monthly', 'meterable' => true\]" "$BACK/config/tenant_plan.php"
check "isMeterable resolves dotted keys literally" hasf "config('tenant_plan.usage_limits', \[\])" "$BACK/app/Services/TenantPlan/TenantUsageMeter.php"

echo "== Admin (read-only) =="
for c in AdminUsageLedgerAnomalyController AdminUsageLedgerRepairSummaryController; do
  check "$c" test -f "$ADMIN_CTRL/$c.php"
done
check "routes register global anomaly summary" hasf "usage-ledger/anomalies" "$BACK/routes/api.php"
check "routes register tenant anomaly summary" hasf "tenants/{tenant}/usage-ledger/anomalies" "$BACK/routes/api.php"
check "routes register repair-summary" hasf "usage-ledger/repair-summary" "$BACK/routes/api.php"

echo "== Commands =="
for c in UsageLedgerAnomalyScanCommand UsageLedgerRepairPlanCommand UsageLedgerRepairApplyCommand UsageLedgerRepairSummaryCommand UsageLedgerGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done
check "repair-apply requires --apply/--reason/--actor" hasf "\-\-reason is required" "$CMD/UsageLedgerRepairApplyCommand.php"

echo "== Config / rules =="
check "usage_ledger_anomaly config exists" test -f "$BACK/config/usage_ledger_anomaly.php"
for r in ULR-R001 ULR-R002 ULR-R003 ULR-R004 ULR-R005 ULR-R006 ULR-R007 ULR-R008 ULR-R009 ULR-R010 ULR-R011 ULR-R012 ULR-R013 ULR-R014 ULR-R015 ULR-R016; do
  check "config locks $r" hasf "$r" "$BACK/config/usage_ledger_anomaly.php"
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_28" hasf "sprint_28" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 28 runtime rule" hasf "Sprint 28 Usage Ledger Anomaly Detection" docs/PROJECT_RULES.md
check "Sprint 27 UEL rules still present" hasf "UEL-R015" docs/PROJECT_RULES.md
check "Sprint 26 TPE rules still present" hasf "TPE-R012" docs/PROJECT_RULES.md
check "Sprint 25 TLS rules still present" hasf "TLS-R004" docs/PROJECT_RULES.md

echo "== CI grep rules =="
check "ULR-R007 grep target present" bash -c "grep -R 'ULR-R007' $BACK/config docs >/dev/null"
check "ULR-R010 grep target present" bash -c "grep -R 'ULR-R010' $BACK/config docs >/dev/null"
check "ULR-R016 grep target present" bash -c "grep -R 'ULR-R016' $BACK/config docs >/dev/null"

echo "== Docs =="
check "architecture doc" test -f "docs/architecture/usage-ledger-anomaly-detection-governed-repair-governance.md"
check "sprint 28 doc" test -f "docs/sprints/sprint-28-usage-ledger-anomaly-detection-governed-repair-foundation.md"

echo "== Tests present =="
for t in UsageLedgerAnomalyDetectorTest UsageLedgerRepairTest UsageLedgerAdminApiTest UsageLedgerRulesLockTest UsageLedgerCommandsTest; do
  check "$t exists" test -f "$BACK/tests/Feature/$t.php"
done

echo "== Security: no ledger/repair mutation route in runtime, no secrets in config =="
check "no runtime mutation route for usage ledger" bash -c \
  "! grep -E 'Route::(post|put|patch|delete).*(usage-events|usage-ledger)' $BACK/routes/api.php"
check "no repair apply/mutation route in runtime" bash -c \
  "! grep -E 'Route::(post|put|patch|delete)[^;]*(repair|usage-ledger)' $BACK/routes/api.php"
check "no server-side ledger repair in Android" bash -c \
  "! grep -R 'UsageLedgerRepairService\|tenant_usage_ledger_repairs' android/app/src/main 2>/dev/null"

echo "== Commands run clean (go-no-go + prior gates) =="
SMOKE_DB="$(mktemp -t sprint28_smoke_XXXXXX.sqlite)"
trap 'rm -f "$SMOKE_DB"' EXIT
( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan migrate --force ) >/dev/null 2>&1
run_art() { ( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan "$@" ); }
check "usage-ledger:anomaly-scan (clean baseline)" run_art usage-ledger:anomaly-scan --json
check "usage-ledger:repair-plan dry-run" run_art usage-ledger:repair-plan --reason=smoke --json
check "usage-ledger:repair-apply --dry-run" run_art usage-ledger:repair-apply --dry-run --reason=smoke --actor=system --json
refuses_without_flags() { ! ( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan usage-ledger:repair-apply >/dev/null 2>&1 ); }
check "usage-ledger:repair-apply refuses without flags" refuses_without_flags
check "usage-ledger:repair-summary" run_art usage-ledger:repair-summary --json
check "usage-ledger:go-no-go" run_art usage-ledger:go-no-go --json
check "report-export-metering:go-no-go (Sprint 27)" run_art report-export-metering:go-no-go --json
check "tenant-plan:go-no-go (Sprint 26)" run_art tenant-plan:go-no-go --json
check "tenant-lifecycle:go-no-go (Sprint 25)" run_art tenant-lifecycle:go-no-go --json

echo "== No secrets leaked in command output =="
no_secret_markers() {
  ! ( cd "$BACK" && DB_CONNECTION=sqlite DB_DATABASE="$SMOKE_DB" php artisan usage-ledger:anomaly-scan --json 2>&1 ) \
    | grep -Eiq 'sk_live|password=|secret=|bearer '
}
check "anomaly-scan output has no secret markers" no_secret_markers

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Sprint 28 smoke: $fail failures  (passed: $pass)"
[ "$fail" -eq 0 ]
