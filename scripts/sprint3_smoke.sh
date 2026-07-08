#!/usr/bin/env bash
#
# Sprint 3 — Android Cashier Foundation smoke test.
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
has() { grep -Rqs "$1" "$2"; }
has_tree() { grep -Rqs "$1" android/app/src/main/java/com/aishtech/poslite; }

APP_SRC="android/app/src/main/java/com/aishtech/poslite"

echo "Sprint 3 smoke test"
echo "-------------------"

# Documentation & foundation
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "Sprint 0 evidence doc exists" test -f docs/sprints/sprint-0-project-setup.md
check "Sprint 1 evidence doc exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "Sprint 2 evidence doc exists" test -f docs/sprints/sprint-2-product-foundation.md
check "Sprint 3 evidence doc exists" test -f docs/sprints/sprint-3-android-cashier-foundation.md

# Application rules lock
check "PROJECT_RULES Foundation Lock Index" has "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 0 Runtime Rule" has "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 1 Multi-Tenant Runtime Rule" has "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 2 Product Foundation Runtime Rule" has "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES Sprint 3 Android Cashier Foundation Runtime Rule" has "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "README references Sprint 3" has "Sprint 3" README.md

# Android project shell
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build.gradle.kts exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package com.aishtech.poslite" has "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" bash -c 'grep -q "minSdk = 26" android/app/build.gradle.kts'
check "targetSdk 35" bash -c 'grep -q "targetSdk = 35" android/app/build.gradle.kts'

# Auth / session
check "LoginActivity exists" test -f "$APP_SRC/feature/auth/LoginActivity.kt"
check "LoginViewModel exists" test -f "$APP_SRC/feature/auth/LoginViewModel.kt"
check "TokenStore exists" test -f "$APP_SRC/core/session/TokenStore.kt"
check "SessionManager exists" test -f "$APP_SRC/core/session/SessionManager.kt"
check "AuthInterceptor exists" test -f "$APP_SRC/core/network/AuthInterceptor.kt"
check "auth login endpoint consumed" has_tree "api/v1/auth/login"
check "password is not persisted" bash -c '! grep -Rqs "savePassword\|putString(\"password\"\|KEY_PASSWORD" android/app/src/main/java/com/aishtech/poslite'

# API client (Retrofit/OkHttp)
check "ApiClient exists" test -f "$APP_SRC/core/network/ApiClient.kt"
check "PosApiService exists" test -f "$APP_SRC/core/network/PosApiService.kt"
check "Retrofit dependency declared" has "Retrofit" android/app/build.gradle.kts
check "OkHttp dependency declared" has "okhttp" android/app/build.gradle.kts

# Room local catalog
check "PosDatabase exists" test -f "$APP_SRC/core/database/PosDatabase.kt"
check "Room dependency declared" has "androidx.room" android/app/build.gradle.kts
check "LocalProductEntity exists" test -f "$APP_SRC/data/local/entity/LocalProductEntity.kt"
check "LocalProductCategoryEntity exists" test -f "$APP_SRC/data/local/entity/LocalProductCategoryEntity.kt"
check "AppSettingEntity exists" test -f "$APP_SRC/data/local/entity/AppSettingEntity.kt"
check "ProductDao exists" test -f "$APP_SRC/data/local/dao/ProductDao.kt"
check "ProductCategoryDao exists" test -f "$APP_SRC/data/local/dao/ProductCategoryDao.kt"
check "AppSettingDao exists" test -f "$APP_SRC/data/local/dao/AppSettingDao.kt"

# Sync + search + cart
check "CatalogSyncManager exists" test -f "$APP_SRC/feature/sync/CatalogSyncManager.kt"
check "sync/products endpoint consumed" has_tree "sync/products"
check "sync/categories endpoint consumed" has_tree "sync/categories"
check "updated_since incremental sync present" has_tree "updated_since"
check "local product search present" has_tree "searchActiveProducts"
check "CartRepository exists" test -f "$APP_SRC/data/repository/CartRepository.kt"
check "CartItem exists" test -f "$APP_SRC/feature/cashier/CartItem.kt"
check "CashierActivity exists" test -f "$APP_SRC/feature/cashier/CashierActivity.kt"

# Tests
check "CartRepositoryTest exists" test -f android/app/src/test/java/com/aishtech/poslite/CartRepositoryTest.kt
check "CatalogMappingTest exists" test -f android/app/src/test/java/com/aishtech/poslite/CatalogMappingTest.kt

# Security: payment gateway secrets must never live in the Android source. (The
# earlier Sprint-3-scoped "no QRIS runtime" / "no printer runtime" negative
# assertions were removed: QRIS shipped in Sprint 5 and printer/receipt in
# Sprint 6, so those features are now legitimately present in later sprints.)
check "no payment gateway key" bash -c '! grep -Rqsi "midtrans\|xendit\|SERVER_KEY\|CLIENT_KEY" android/app/src/main/java/com/aishtech/poslite'

# Backend compatibility (routes still wired)
check "backend auth login route exists" has "auth/login" backend/routes/api.php
check "backend sync products route exists" has "sync/products" backend/routes/api.php
check "backend sync categories route exists" has "sync/categories" backend/routes/api.php

# Hygiene: forbidden files must not be committed
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor committed" bash -c '! git ls-files | grep -qE "(^|/)vendor/"'
check "no node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)node_modules/"'
check "no apk/aab committed" bash -c '! git ls-files | grep -qE "\.(apk|aab)$"'
check "no android build output committed" bash -c '! git ls-files | grep -qE "(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite/db file committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo "-------------------"
echo "pass: $pass  fail: $fail"

if [ "$fail" -ne 0 ]; then
  exit 1
fi
