#!/usr/bin/env bash
#
# Sprint 27 — Report Export Metering & Usage Event Ledger Governance Foundation
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
SVC="$BACK/app/Services/UsageEventLedger"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"

echo "== Migration / model =="
check "tenant_usage_events migration exists" bash -c "ls $BACK/database/migrations/*create_tenant_usage_events_table.php"
check "TenantUsageEvent model" test -f "$BACK/app/Models/TenantUsageEvent.php"
check "migration is append-only (unique idempotency)" hasf "unique(\['tenant_id', 'idempotency_key'\])" "$BACK"/database/migrations/*create_tenant_usage_events_table.php

echo "== Services =="
for s in UsageEventLedgerService UsageEventRecorder UsageEventPeriodResolver UsageEventDecision SanitizesUsageEventMetadata ReportExportMeteringService ReportExportMeteringSummaryService UsageEventLedgerReadinessService ReportExportMeteringEnforcementAuditService ReportExportMeteringGoNoGoService; do
  check "$s" test -f "$SVC/$s.php"
done
check "metering reads ledger for current usage" hasf "currentMonthlyUsage" "$SVC/ReportExportMeteringService.php"
check "recorder is idempotent" hasf "idempotency_key" "$SVC/UsageEventRecorder.php"

echo "== Meter wiring =="
check "TenantUsageMeter meters reports.exports.monthly" hasf "reports.exports.monthly" "$BACK/app/Services/TenantPlan/TenantUsageMeter.php"
check "reports.exports.monthly is meterable true" hasf "'reports.exports.monthly' => \['label' => 'Report exports per month', 'period' => 'monthly', 'meterable' => true\]" "$BACK/config/tenant_plan.php"
check "route wires usage limit guard on export" hasf "tenant.usage.limit:reports.exports.monthly" "$BACK/routes/api.php"
check "controller records export event" hasf "recordExport" "$ADMIN_CTRL/../Reports/DailySalesCsvExportController.php"

echo "== Admin (read-only) =="
for c in AdminTenantUsageEventController AdminUsageEventLedgerSummaryController AdminReportExportMeteringSummaryController; do
  check "$c" test -f "$ADMIN_CTRL/$c.php"
done
check "UsageEventResource" test -f "$ADMIN_RES/UsageEventResource.php"
check "routes register tenant usage-events" hasf "tenants/{tenant}/usage-events" "$BACK/routes/api.php"
check "routes register usage-event-ledger summary" hasf "usage-event-ledger/summary" "$BACK/routes/api.php"
check "routes register report-export-metering summary" hasf "report-export-metering/summary" "$BACK/routes/api.php"

echo "== Commands =="
for c in UsageEventLedgerReadinessCommand UsageEventLedgerSummaryCommand ReportExportMeteringSummaryCommand ReportExportMeteringEnforcementAuditCommand ReportExportMeteringGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done

echo "== Config / rules =="
check "usage_event_ledger config exists" test -f "$BACK/config/usage_event_ledger.php"
for r in UEL-R001 UEL-R002 UEL-R003 UEL-R004 UEL-R005 UEL-R006 UEL-R007 UEL-R008 UEL-R009 UEL-R010 UEL-R011 UEL-R012 UEL-R013 UEL-R014 UEL-R015; do
  check "config locks $r" hasf "$r" "$BACK/config/usage_event_ledger.php"
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_27" hasf "sprint_27" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 27 runtime rule" hasf "Sprint 27 Report Export Metering" docs/PROJECT_RULES.md
check "Sprint 26 TPE rules still present" hasf "TPE-R004" docs/PROJECT_RULES.md
check "Sprint 25 TLS rules still present" hasf "TLS-R004" docs/PROJECT_RULES.md

echo "== CI grep rules =="
check "UEL-R004 grep target present" bash -c "grep -R 'UEL-R004' $BACK/config docs >/dev/null"
check "UEL-R008 grep target present" bash -c "grep -R 'UEL-R008' $BACK/config docs >/dev/null"
check "UEL-R015 grep target present" bash -c "grep -R 'UEL-R015' $BACK/config docs >/dev/null"

echo "== Docs =="
check "architecture doc" test -f "docs/architecture/report-export-metering-usage-event-ledger-governance.md"
check "sprint 27 doc" test -f "docs/sprints/sprint-27-report-export-metering-usage-event-ledger-governance-foundation.md"

echo "== Tests present =="
for t in UsageEventLedgerTest ReportExportMeteringTest UsageEventAdminApiTest UsageEventLedgerCommandsTest UsageEventRulesLockTest; do
  check "$t exists" test -f "$BACK/tests/Feature/$t.php"
done

echo "== Security: no ledger mutation route in runtime, no secrets in config =="
check "no runtime update/delete usage-events route" bash -c \
  "! grep -E 'usage-events.*(destroy|update|delete)|Route::(delete|put|patch).*usage-events' $BACK/routes/api.php"
check "no server-side ledger enforcement in Android" bash -c \
  "! grep -R 'UsageEventLedgerService\|ReportExportMeteringService\|tenant_usage_events' android/app/src/main 2>/dev/null"

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Sprint 27 smoke: $fail failures  (passed: $pass)"
[ "$fail" -eq 0 ]
