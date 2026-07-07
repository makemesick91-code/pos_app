#!/usr/bin/env bash
#
# Sprint 6 — Printer & Receipt Foundation smoke test.
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

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "sprint 0 evidence exists" test -f docs/sprints/sprint-0-project-setup.md
check "sprint 1 evidence exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "sprint 2 evidence exists" test -f docs/sprints/sprint-2-product-foundation.md
check "sprint 3 evidence exists" test -f docs/sprints/sprint-3-android-cashier-foundation.md
check "sprint 4 evidence exists" test -f docs/sprints/sprint-4-sales-backend-integration.md
check "sprint 5 evidence exists" test -f docs/sprints/sprint-5-qris-payment-gateway-foundation.md
check "sprint 6 evidence exists" test -f docs/sprints/sprint-6-printer-receipt-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 1 Multi-Tenant Runtime Rule" hasf "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 2 Product Foundation Runtime Rule" hasf "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 3 Android Cashier Foundation Runtime Rule" hasf "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 4 Sales Backend Integration Runtime Rule" hasf "Sprint 4 Sales Backend Integration Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" hasf "Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 6 Printer & Receipt Foundation Runtime Rule" hasf "Sprint 6 Printer & Receipt Foundation Runtime Rule" docs/PROJECT_RULES.md

echo "== Backend receipt API =="
check "ReceiptController exists" test -f backend/app/Http/Controllers/Api/V1/ReceiptController.php
check "ReceiptService exists" test -f backend/app/Services/ReceiptService.php
check "ReceiptResource exists" test -f backend/app/Http/Resources/Api/V1/ReceiptResource.php
check "receipt route registered" has "sales/{sale}/receipt" backend/routes/api.php
check "config marks receipt backend authoritative" hasf "receipt_backend_authoritative" backend/config/pos_foundation.php
check "config lists sprint_6" hasf "sprint_6" backend/config/pos_foundation.php

echo "== Backend receipt tests =="
check "receipt api test exists" test -f backend/tests/Feature/ReceiptApiTest.php
check "receipt tenant isolation test exists" test -f backend/tests/Feature/ReceiptTenantIsolationTest.php

echo "== Android Gradle wrapper =="
check "gradlew exists" test -f android/gradlew
check "gradlew executable" test -x android/gradlew
check "gradlew.bat exists" test -f android/gradlew.bat
check "gradle-wrapper.jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "gradle-wrapper.properties exists" test -f android/gradle/wrapper/gradle-wrapper.properties
check "wrapper distributionUrl set" hasf "distributionUrl" android/gradle/wrapper/gradle-wrapper.properties

echo "== Android shell integrity =="
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build.gradle.kts exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" bash -c 'grep -q "minSdk = 26" android/app/build.gradle.kts'
check "targetSdk 35" bash -c 'grep -q "targetSdk = 35" android/app/build.gradle.kts'

echo "== Android receipt & printer =="
check "Receipt DTO exists" test -f "$APP/data/remote/dto/ReceiptDtos.kt"
check "ReceiptRepository exists" test -f "$APP/data/repository/ReceiptRepository.kt"
check "ReceiptActivity exists" test -f "$APP/feature/receipt/ReceiptActivity.kt"
check "ReceiptViewModel exists" test -f "$APP/feature/receipt/ReceiptViewModel.kt"
check "receipt layout exists" test -f android/app/src/main/res/layout/activity_receipt.xml
check "EscPosReceiptFormatter exists" test -f "$APP/feature/printer/EscPosReceiptFormatter.kt"
check "PrinterConnection exists" test -f "$APP/feature/printer/PrinterConnection.kt"
check "BluetoothPrinterConnection exists" test -f "$APP/feature/printer/BluetoothPrinterConnection.kt"
check "PrinterRepository exists" test -f "$APP/feature/printer/PrinterRepository.kt"
check "PrinterSettingsStore exists" test -f "$APP/feature/printer/PrinterSettingsStore.kt"
check "PosApiService has receipt endpoint" has "sales/{id}/receipt" "$APP/core/network/PosApiService.kt"
check "ReceiptActivity registered in manifest" hasf "ReceiptActivity" android/app/src/main/AndroidManifest.xml
check "Bluetooth permission in manifest" hasf "BLUETOOTH" android/app/src/main/AndroidManifest.xml
check "print button eligibility guarded" has "printable" "$APP/feature/printer/PrinterRepository.kt"

echo "== Android tests =="
check "EscPos formatter test exists" test -f android/app/src/test/java/com/aishtech/poslite/EscPosReceiptFormatterTest.kt
check "Receipt repository test exists" test -f android/app/src/test/java/com/aishtech/poslite/ReceiptRepositoryTest.kt

echo "== CI workflow =="
check "sprint6-ci workflow exists" test -f .github/workflows/sprint6-ci.yml
check "sprint6-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint6-ci.yml
check "sprint6-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint6-ci.yml

echo "== Security: no gateway secrets / no local secrets in Android =="
check "no payment gateway key in Android source" bash -c '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "printer settings store persists printer-only fields" has "paper_width_mm" "$APP/feature/printer/PrinterSettingsStore.kt"

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
