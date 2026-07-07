#!/usr/bin/env bash
#
# Sprint 7 — Offline Cash & Sync Foundation smoke test.
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

echo "== Backend offline sync / idempotency =="
check "offline sync migration exists" bash -c 'ls backend/database/migrations/*add_offline_sync_fields_to_sales_table.php'
check "sales request accepts client_reference" has "client_reference" backend/app/Http/Requests/Api/V1/StoreSaleRequest.php
check "sales request accepts source" has "ANDROID_OFFLINE" backend/app/Http/Requests/Api/V1/StoreSaleRequest.php
check "SaleService handles client_reference idempotency" has "client_reference" backend/app/Services/SaleService.php
check "SaleService has findByClientReference" has "findByClientReference" backend/app/Services/SaleService.php
check "Sale model has ANDROID_OFFLINE source" has "SOURCE_ANDROID_OFFLINE" backend/app/Models/Sale.php
check "SaleResource exposes idempotent_replay" has "idempotent_replay" backend/app/Http/Resources/Api/V1/SaleResource.php
check "config marks offline_qris_forbidden" hasf "offline_qris_forbidden" backend/config/pos_foundation.php
check "config marks sales_idempotency_required" hasf "sales_idempotency_required" backend/config/pos_foundation.php
check "config lists sprint_7" hasf "sprint_7" backend/config/pos_foundation.php

echo "== Backend offline sync tests =="
check "offline sales sync api test exists" test -f backend/tests/Feature/OfflineSalesSyncApiTest.php
check "sales idempotency test exists" test -f backend/tests/Feature/SalesIdempotencyTest.php
check "offline sales tenant isolation test exists" test -f backend/tests/Feature/OfflineSalesTenantIsolationTest.php

echo "== Android Gradle wrapper =="
check "gradlew exists" test -f android/gradlew
check "gradlew executable" test -x android/gradlew
check "gradlew.bat exists" test -f android/gradlew.bat
check "gradle-wrapper.jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "gradle-wrapper.properties exists" test -f android/gradle/wrapper/gradle-wrapper.properties

echo "== Android shell integrity =="
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build.gradle.kts exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" bash -c 'grep -q "minSdk = 26" android/app/build.gradle.kts'
check "targetSdk 35" bash -c 'grep -q "targetSdk = 35" android/app/build.gradle.kts'
check "WorkManager dependency exists" has "work-runtime-ktx" android/app/build.gradle.kts

echo "== Android offline storage & sync =="
check "LocalOfflineSaleEntity exists" test -f "$APP/data/local/entity/LocalOfflineSaleEntity.kt"
check "LocalOfflineSaleItemEntity exists" test -f "$APP/data/local/entity/LocalOfflineSaleItemEntity.kt"
check "OfflineSyncStatus exists" test -f "$APP/data/local/OfflineSyncStatus.kt"
check "OfflineSaleDao exists" test -f "$APP/data/local/dao/OfflineSaleDao.kt"
check "OfflineSaleItemDao exists" test -f "$APP/data/local/dao/OfflineSaleItemDao.kt"
check "OfflineSaleRepository exists" test -f "$APP/data/repository/OfflineSaleRepository.kt"
check "OfflineSalesSyncWorker exists" test -f "$APP/feature/sync/OfflineSalesSyncWorker.kt"
check "OfflineSalesSyncScheduler exists" test -f "$APP/feature/sync/OfflineSalesSyncScheduler.kt"
check "NetworkMonitor exists" test -f "$APP/core/network/NetworkMonitor.kt"
check "QRIS online-only guard exists" test -f "$APP/feature/qris/QrisOnlineOnlyGuard.kt"
check "database registers offline sale entities" has "LocalOfflineSaleEntity" "$APP/core/database/PosDatabase.kt"
check "ServiceLocator wires offlineSaleRepository" has "offlineSaleRepository" "$APP/core/ServiceLocator.kt"
check "insertOfflineSaleWithItems present" has "insertOfflineSaleWithItems" "$APP/data/local/dao/OfflineSaleDao.kt"
check "worker uses retry" has "Result.retry" "$APP/feature/sync/OfflineSalesSyncWorker.kt"
check "scheduler uses exponential backoff" has "EXPONENTIAL" "$APP/feature/sync/OfflineSalesSyncScheduler.kt"
check "scheduler requires network connected" has "NetworkType.CONNECTED" "$APP/feature/sync/OfflineSalesSyncScheduler.kt"
check "QRIS guard wired into QRIS ViewModel" has "QrisOnlineOnlyGuard" "$APP/feature/qris/QrisPaymentViewModel.kt"

echo "== Android cashier offline UI =="
check "Cashier UI has offline cash action" has "checkoutCashOffline" "$APP/feature/cashier/CashierViewModel.kt"
check "offline checkout button in layout" hasf "buttonCheckoutOffline" android/app/src/main/res/layout/activity_cashier.xml
check "manual sync action in layout" hasf "buttonSyncNow" android/app/src/main/res/layout/activity_cashier.xml
check "sync status summary string exists" hasf "cashier_sync_summary" android/app/src/main/res/values/strings.xml
check "offline draft receipt label exists" hasf "OFFLINE / BELUM SYNC" android/app/src/main/res/values/strings.xml
check "QRIS online-only message exists" has "QRIS membutuhkan koneksi internet" "$APP/feature/qris/QrisOnlineOnlyGuard.kt"

echo "== Android offline tests =="
check "OfflineSaleRepository test exists" test -f "$TEST/OfflineSaleRepositoryTest.kt"
check "Offline sync logic test exists" test -f "$TEST/OfflineSalesSyncLogicTest.kt"
check "Offline sale mapping test exists" test -f "$TEST/OfflineSaleMappingTest.kt"
check "QRIS online-only guard test exists" test -f "$TEST/QrisOnlineOnlyGuardTest.kt"

echo "== CI workflow =="
check "sprint7-ci workflow exists" test -f .github/workflows/sprint7-ci.yml
check "sprint7-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint7-ci.yml
check "sprint7-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint7-ci.yml

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
