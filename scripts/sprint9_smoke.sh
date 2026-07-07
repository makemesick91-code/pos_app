#!/usr/bin/env bash
#
# Sprint 9 — Reports & Closing Foundation smoke test.
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

# Custom grep helpers (silent).
has() { grep -Rq "$1" "$2"; }
hasf() { grep -q "$1" "$2"; }

APP=android/app/src/main/java/com/aishtech/poslite
TEST=android/app/src/test/java/com/aishtech/poslite

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "sprint 0 evidence exists" test -f docs/sprints/sprint-0-project-setup.md
check "sprint 1 evidence exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "sprint 2 evidence exists" test -f docs/sprints/sprint-2-product-foundation.md
check "sprint 3 evidence exists" test -f docs/sprints/sprint-3-android-cashier-foundation.md
check "sprint 4 evidence exists" test -f docs/sprints/sprint-4-sales-backend-integration.md
check "sprint 5 evidence exists" test -f docs/sprints/sprint-5-qris-payment-gateway-foundation.md
check "sprint 6 evidence exists" test -f docs/sprints/sprint-6-printer-receipt-foundation.md
check "sprint 7 evidence exists" test -f docs/sprints/sprint-7-offline-cash-sync-foundation.md
check "sprint 8 evidence exists" test -f docs/sprints/sprint-8-inventory-simple-foundation.md
check "sprint 9 evidence exists" test -f docs/sprints/sprint-9-reports-closing-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 1 Multi-Tenant Runtime Rule" hasf "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 2 Product Foundation Runtime Rule" hasf "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 3 Android Cashier Foundation Runtime Rule" hasf "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 4 Sales Backend Integration Runtime Rule" hasf "Sprint 4 Sales Backend Integration Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" hasf "Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 6 Printer & Receipt Foundation Runtime Rule" hasf "Sprint 6 Printer & Receipt Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 7 Offline Cash & Sync Foundation Runtime Rule" hasf "Sprint 7 Offline Cash & Sync Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 8 Inventory Simple Foundation Runtime Rule" hasf "Sprint 8 Inventory Simple Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 9 Reports & Closing Foundation Runtime Rule" hasf "Sprint 9 Reports & Closing Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 9" hasf "sprint-9-reports-closing-foundation.md" docs/PROJECT_RULES.md

echo "== Backend reports & closing =="
check "daily_closings migration exists" bash -c 'ls backend/database/migrations/*create_daily_closings_table.php'
check "DailyClosing model exists" test -f backend/app/Models/DailyClosing.php
check "DailySalesReportService exists" test -f backend/app/Services/Reports/DailySalesReportService.php
check "PaymentSummaryReportService exists" test -f backend/app/Services/Reports/PaymentSummaryReportService.php
check "InventoryMovementSummaryService exists" test -f backend/app/Services/Reports/InventoryMovementSummaryService.php
check "DailyClosingService exists" test -f backend/app/Services/Reports/DailyClosingService.php
check "CsvReportExporter exists" test -f backend/app/Services/Reports/CsvReportExporter.php
check "daily sales report controller exists" test -f backend/app/Http/Controllers/Api/V1/Reports/DailySalesReportController.php
check "payment summary controller exists" test -f backend/app/Http/Controllers/Api/V1/Reports/PaymentSummaryReportController.php
check "inventory movement summary controller exists" test -f backend/app/Http/Controllers/Api/V1/Reports/InventoryMovementSummaryController.php
check "csv export controller exists" test -f backend/app/Http/Controllers/Api/V1/Reports/DailySalesCsvExportController.php
check "daily closing controller exists" test -f backend/app/Http/Controllers/Api/V1/DailyClosingController.php
check "report date filter request exists" test -f backend/app/Http/Requests/Api/V1/ReportDateFilterRequest.php
check "store daily closing request exists" test -f backend/app/Http/Requests/Api/V1/StoreDailyClosingRequest.php
check "index daily closing request exists" test -f backend/app/Http/Requests/Api/V1/IndexDailyClosingRequest.php
check "daily sales resource exists" test -f backend/app/Http/Resources/Api/V1/DailySalesReportResource.php
check "payment summary resource exists" test -f backend/app/Http/Resources/Api/V1/PaymentSummaryResource.php
check "inventory movement summary resource exists" test -f backend/app/Http/Resources/Api/V1/InventoryMovementSummaryResource.php
check "daily closing resource exists" test -f backend/app/Http/Resources/Api/V1/DailyClosingResource.php

echo "== Backend routes =="
check "daily sales route exists" hasf "reports/daily-sales" backend/routes/api.php
check "payment summary route exists" hasf "reports/payment-summary" backend/routes/api.php
check "inventory summary route exists" hasf "reports/inventory-movements-summary" backend/routes/api.php
check "csv export route exists" hasf "reports/daily-sales/export.csv" backend/routes/api.php
check "closing routes exist" hasf "closings/daily" backend/routes/api.php

echo "== Backend tests =="
check "daily sales report test exists" test -f backend/tests/Feature/DailySalesReportApiTest.php
check "payment summary report test exists" test -f backend/tests/Feature/PaymentSummaryReportApiTest.php
check "inventory movement summary test exists" test -f backend/tests/Feature/InventoryMovementSummaryReportApiTest.php
check "daily closing test exists" test -f backend/tests/Feature/DailyClosingApiTest.php
check "csv export test exists" test -f backend/tests/Feature/ReportCsvExportTest.php
check "report tenant isolation test exists" test -f backend/tests/Feature/ReportTenantIsolationTest.php

echo "== Android app foundation =="
check "Android Gradle wrapper exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "android gradlew executable" test -x android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== Android reports & closing =="
check "ReportDtos exists" test -f "$APP/data/remote/dto/ReportDtos.kt"
check "ClosingDtos exists" test -f "$APP/data/remote/dto/ClosingDtos.kt"
check "ReportRepository exists" test -f "$APP/data/repository/ReportRepository.kt"
check "ClosingRepository exists" test -f "$APP/data/repository/ClosingRepository.kt"
check "ReportsActivity exists" test -f "$APP/feature/reports/ReportsActivity.kt"
check "ReportsViewModel exists" test -f "$APP/feature/reports/ReportsViewModel.kt"
check "reports layout exists" test -f android/app/src/main/res/layout/activity_reports.xml
check "PosApiService has reports endpoints" hasf "reports/daily-sales" "$APP/core/network/PosApiService.kt"
check "PosApiService has closings endpoint" hasf "closings/daily" "$APP/core/network/PosApiService.kt"
check "report Android DTO mapping test exists" test -f "$TEST/ReportDtoMappingTest.kt"
check "report Android display state test exists" test -f "$TEST/ReportDisplayStateTest.kt"
check "reports screen strings present" has "Ringkasan Harian" android/app/src/main/res/values/strings.xml
check "close today string present" has "Tutup Hari" android/app/src/main/res/values/strings.xml

echo "== CI workflow =="
check "sprint9-ci workflow exists" test -f .github/workflows/sprint9-ci.yml
check "sprint9-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint9-ci.yml
check "sprint9-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint9-ci.yml

echo "== Security: no gateway secrets in Android =="
check "no payment gateway key in Android source" bash -c '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
