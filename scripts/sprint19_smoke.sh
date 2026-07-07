#!/usr/bin/env bash
#
# Sprint 19 — Production Operations Baseline & Post-Handover Governance smoke test.
# Structural validation only; does not build the Android app or run a database.
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
SVC="$BACK/app/Services/Operations"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"
OPS=docs/operations

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 19 evidence exists" test -f docs/sprints/sprint-19-production-operations-post-handover-governance-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 18 Runtime Rule" hasf "Sprint 18 Pilot Closure & Production Handover Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 19 Runtime Rule" hasf "Sprint 19 Production Operations Baseline & Post-Handover Governance Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 19" hasf "sprint-19-production-operations-post-handover-governance-foundation.md" docs/PROJECT_RULES.md

echo "== Migrations =="
check "production_operation_runs migration exists" bash -c "ls $MIG/*create_production_operation_runs_table.php >/dev/null 2>&1"
check "production_incidents migration exists" bash -c "ls $MIG/*create_production_incidents_table.php >/dev/null 2>&1"
check "production_maintenance_windows migration exists" bash -c "ls $MIG/*create_production_maintenance_windows_table.php >/dev/null 2>&1"

echo "== Models =="
check "ProductionOperationRun model exists" test -f "$MODELS/ProductionOperationRun.php"
check "ProductionIncident model exists" test -f "$MODELS/ProductionIncident.php"
check "ProductionMaintenanceWindow model exists" test -f "$MODELS/ProductionMaintenanceWindow.php"
check "ProductionOperationRun status constants present" hasf "STATUS_BLOCKED" "$MODELS/ProductionOperationRun.php"
check "ProductionIncident severity constants present" hasf "SEVERITY_P0" "$MODELS/ProductionIncident.php"
check "ProductionMaintenanceWindow risk constants present" hasf "RISK_CRITICAL" "$MODELS/ProductionMaintenanceWindow.php"

echo "== Services =="
check "ProductionOperationsHealthService exists" test -f "$SVC/ProductionOperationsHealthService.php"
check "ProductionIncidentService exists" test -f "$SVC/ProductionIncidentService.php"
check "BackupRestoreGovernanceService exists" test -f "$SVC/BackupRestoreGovernanceService.php"
check "SupportSlaGovernanceService exists" test -f "$SVC/SupportSlaGovernanceService.php"
check "MaintenanceWindowService exists" test -f "$SVC/MaintenanceWindowService.php"
check "ReleaseRollbackGovernanceService exists" test -f "$SVC/ReleaseRollbackGovernanceService.php"
check "PostHandoverGovernanceReportService exists" test -f "$SVC/PostHandoverGovernanceReportService.php"

echo "== Config =="
check "production_operations config exists" test -f "$BACK/config/production_operations.php"
check "pos_foundation lists sprint_19" hasf "sprint_19" "$BACK/config/pos_foundation.php"
check "pos_foundation has production operations rule" hasf "production_operations_baseline_required" "$BACK/config/pos_foundation.php"

echo "== Admin operations APIs =="
check "ProductionOperationRunController exists" test -f "$ADMIN_CTRL/ProductionOperationRunController.php"
check "ProductionIncidentController exists" test -f "$ADMIN_CTRL/ProductionIncidentController.php"
check "ProductionMaintenanceWindowController exists" test -f "$ADMIN_CTRL/ProductionMaintenanceWindowController.php"
check "ProductionOpsHealthController exists" test -f "$ADMIN_CTRL/ProductionOpsHealthController.php"
check "ProductionIncidentSummaryController exists" test -f "$ADMIN_CTRL/ProductionIncidentSummaryController.php"
check "ProductionPostHandoverGoNoGoController exists" test -f "$ADMIN_CTRL/ProductionPostHandoverGoNoGoController.php"
check "StoreProductionIncidentRequest exists" test -f "$ADMIN_REQ/StoreProductionIncidentRequest.php"
check "AcceptProductionIncidentRiskRequest exists" test -f "$ADMIN_REQ/AcceptProductionIncidentRiskRequest.php"
check "StoreProductionMaintenanceWindowRequest exists" test -f "$ADMIN_REQ/StoreProductionMaintenanceWindowRequest.php"
check "StoreProductionOperationRunRequest exists" test -f "$ADMIN_REQ/StoreProductionOperationRunRequest.php"
check "ProductionOperationRunResource exists" test -f "$ADMIN_RES/ProductionOperationRunResource.php"
check "ProductionIncidentResource exists" test -f "$ADMIN_RES/ProductionIncidentResource.php"
check "ProductionMaintenanceWindowResource exists" test -f "$ADMIN_RES/ProductionMaintenanceWindowResource.php"
check "PostHandoverGovernanceReportResource exists" test -f "$ADMIN_RES/PostHandoverGovernanceReportResource.php"
check "routes register production-operation-runs" hasf "production-operation-runs" "$BACK/routes/api.php"
check "routes register production-incidents" hasf "production-incidents" "$BACK/routes/api.php"
check "routes register production-maintenance-windows" hasf "production-maintenance-windows" "$BACK/routes/api.php"
check "routes register production-post-handover-go-no-go" hasf "production-post-handover-go-no-go" "$BACK/routes/api.php"
check "operations behind platform.admin group" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Commands =="
check "production:ops-health command exists" test -f "$CMD/ProductionOpsHealthCommand.php"
check "production:incident-summary command exists" test -f "$CMD/ProductionIncidentSummaryCommand.php"
check "production:backup-governance-check command exists" test -f "$CMD/ProductionBackupGovernanceCheckCommand.php"
check "production:post-handover-go-no-go command exists" test -f "$CMD/ProductionPostHandoverGoNoGoCommand.php"
check "ops-health supports --json" hasf "json" "$CMD/ProductionOpsHealthCommand.php"
check "incident-summary supports --strict" hasf "strict" "$CMD/ProductionIncidentSummaryCommand.php"
check "backup-governance-check supports --json" hasf "json" "$CMD/ProductionBackupGovernanceCheckCommand.php"
check "post-handover-go-no-go supports --strict" hasf "strict" "$CMD/ProductionPostHandoverGoNoGoCommand.php"

echo "== Operations docs =="
check "production-operations-runbook exists" test -f "$OPS/production-operations-runbook.md"
check "incident-response-runbook exists" test -f "$OPS/incident-response-runbook.md"
check "backup-restore-governance exists" test -f "$OPS/backup-restore-governance.md"
check "support-sla-operations exists" test -f "$OPS/support-sla-operations.md"
check "maintenance-window-governance exists" test -f "$OPS/maintenance-window-governance.md"
check "release-rollback-governance exists" test -f "$OPS/release-rollback-governance.md"
check "production-health-signals exists" test -f "$OPS/production-health-signals.md"
check "post-handover-governance-report exists" test -f "$OPS/post-handover-governance-report.md"
check "production-operations-go-watch-no-go exists" test -f "$OPS/production-operations-go-watch-no-go.md"

echo "== Tests =="
check "ProductionOperationsHealthServiceTest exists" test -f "$TESTS/ProductionOperationsHealthServiceTest.php"
check "ProductionIncidentServiceTest exists" test -f "$TESTS/ProductionIncidentServiceTest.php"
check "BackupRestoreGovernanceServiceTest exists" test -f "$TESTS/BackupRestoreGovernanceServiceTest.php"
check "SupportSlaGovernanceServiceTest exists" test -f "$TESTS/SupportSlaGovernanceServiceTest.php"
check "MaintenanceWindowServiceTest exists" test -f "$TESTS/MaintenanceWindowServiceTest.php"
check "ReleaseRollbackGovernanceServiceTest exists" test -f "$TESTS/ReleaseRollbackGovernanceServiceTest.php"
check "PostHandoverGovernanceReportServiceTest exists" test -f "$TESTS/PostHandoverGovernanceReportServiceTest.php"
check "ProductionOperationsAdminApiTest exists" test -f "$TESTS/ProductionOperationsAdminApiTest.php"
check "ProductionOperationsCommandsTest exists" test -f "$TESTS/ProductionOperationsCommandsTest.php"
check "ProductionOperationsSecurityScanTest exists" test -f "$TESTS/ProductionOperationsSecurityScanTest.php"
check "ProductionOperationsRegressionRouteTest exists" test -f "$TESTS/ProductionOperationsRegressionRouteTest.php"

echo "== Sprint 13-18 foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:rc-check command intact" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:deployment-check command intact" hasf "pilot:deployment-check" "$CMD/PilotDeploymentCheckCommand.php"
check "pilot:daily-monitoring-check command intact" hasf "pilot:daily-monitoring-check" "$CMD/PilotDailyMonitoringCheckCommand.php"
check "pilot:stabilization-go-no-go command intact" hasf "pilot:stabilization-go-no-go" "$CMD/PilotStabilizationGoNoGoCommand.php"
check "pilot:closure-check command intact" hasf "pilot:closure-check" "$CMD/PilotClosureCheckCommand.php"
check "production:handover-go-no-go command intact" hasf "production:handover-go-no-go" "$CMD/ProductionHandoverGoNoGoCommand.php"

echo "== Android release readiness =="
check "android_release_readiness.sh exists" test -f scripts/android_release_readiness.sh
check "android_release_readiness.sh executable" test -x scripts/android_release_readiness.sh
check "Android Gradle wrapper jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint19-ci workflow exists" test -f .github/workflows/sprint19-ci.yml
check "sprint19-ci runs sprint19 smoke" hasf "sprint19_smoke.sh" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs pilot:deployment-check" hasf "pilot:deployment-check" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs pilot:daily-monitoring-check" hasf "pilot:daily-monitoring-check" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs pilot:stabilization-go-no-go" hasf "pilot:stabilization-go-no-go" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs pilot:closure-check" hasf "pilot:closure-check" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs production:handover-go-no-go" hasf "production:handover-go-no-go" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs production:ops-health" hasf "production:ops-health" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs production:incident-summary" hasf "production:incident-summary" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs production:backup-governance-check" hasf "production:backup-governance-check" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs production:post-handover-go-no-go" hasf "production:post-handover-go-no-go" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint19-ci.yml
check "sprint19-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint19-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/operations/production panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity\|MonitoringActivity\|HypercareActivity\|StabilizationActivity\|HandoverActivity\|ProductionActivity\|OperationsActivity\|IncidentActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
