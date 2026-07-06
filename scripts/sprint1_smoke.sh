#!/usr/bin/env bash
#
# Sprint 1 — SaaS Tenant Foundation smoke test.
# Structural validation only; does not require a running database.
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

# Custom grep-in-file helper (silent).
has() { grep -Rqs "$1" "$2"; }

echo "Sprint 1 smoke test"
echo "-------------------"

# Documentation & foundation
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "Sprint 1 evidence doc exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "PROJECT_RULES multi-tenant runtime rule" has "Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "README references Sprint 1" has "Sprint 1" README.md

# Migrations
check "tenants migration exists" bash -c 'ls backend/database/migrations/*create_tenants_table.php'
check "stores migration exists" bash -c 'ls backend/database/migrations/*create_stores_table.php'
check "users tenant migration exists" bash -c 'ls backend/database/migrations/*add_tenant_fields_to_users_table.php'
check "sanctum tokens migration exists" bash -c 'ls backend/database/migrations/*create_personal_access_tokens_table.php'

# Models
check "Tenant model exists" test -f backend/app/Models/Tenant.php
check "Store model exists" test -f backend/app/Models/Store.php
check "User has tenant relationship" has "function tenant" backend/app/Models/User.php
check "User has store relationship" has "function store" backend/app/Models/User.php
check "User HasApiTokens" has "HasApiTokens" backend/app/Models/User.php

# Sanctum availability
check "Sanctum in composer.json" has "laravel/sanctum" backend/composer.json
check "Sanctum in composer.lock" has "laravel/sanctum" backend/composer.lock

# Tenant context & middleware
check "TenantContext support class exists" test -f backend/app/Support/TenantContext.php
check "SetTenantContext middleware exists" test -f backend/app/Http/Middleware/SetTenantContext.php
check "EnsureTenantIsActive middleware exists" test -f backend/app/Http/Middleware/EnsureTenantIsActive.php
check "middleware aliases registered" has "tenant.context" backend/bootstrap/app.php

# Routes
check "login route defined" has "auth/login" backend/routes/api.php
check "me route defined" has "auth/me" backend/routes/api.php
check "logout route defined" has "auth/logout" backend/routes/api.php
check "tenant-context route defined" has "tenant-context" backend/routes/api.php

# Tests
check "AuthApiTest exists" test -f backend/tests/Feature/AuthApiTest.php
check "TenantContextTest exists" test -f backend/tests/Feature/TenantContextTest.php
check "TenantIsolationTest exists" test -f backend/tests/Feature/TenantIsolationTest.php

# Hygiene — forbidden files must NOT be tracked by git.
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor committed" bash -c '! git ls-files | grep -qE "(^|/)vendor/"'
check "no node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)node_modules/"'
check "no sqlite/db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo "-------------------"
echo "passed: $pass, failed: $fail"

if [ "$fail" -ne 0 ]; then
  exit 1
fi
echo "Sprint 1 smoke: PASS"
