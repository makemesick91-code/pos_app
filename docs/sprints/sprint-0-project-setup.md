# Sprint 0 — Project Setup

## Objective

Establish the initial, foundation-aligned repository structure for the Aish POS
Lite Android POS SaaS: a Laravel API skeleton with a health endpoint, a native
Android Kotlin skeleton, validation scripts, lightweight CI, and sprint evidence.
No business POS features are implemented in this sprint.

## Source of Truth

- [`docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`](../foundation/POS_ANDROID_SAAS_FOUNDATION.md)
- [`docs/PROJECT_RULES.md`](../PROJECT_RULES.md)

All decisions in this sprint conform to the foundation. Nothing here contradicts it.

## Scope

In scope:

- Controlled monorepo layout (`backend/`, `android/`, `docs/`, `scripts/`, `.github/`)
- Laravel API skeleton + `GET /api/health`
- Android Kotlin skeleton (package, SDK levels, launchable placeholder)
- Smoke validation script + Sprint 0 CI workflow
- Documentation / gitignore hardening

Explicitly out of scope (later sprints): tenant CRUD, product CRUD, sales, QRIS,
payment webhooks, offline sync, printer integration, subscription logic.

## Architecture Setup

Single controlled monorepo for Sprint 0 to keep initial setup easy to govern.
This does not preclude a future repository split.

```text
pos_app/
├── backend/     Laravel 13 API (PostgreSQL target; SQLite for tests)
├── android/     Native Android Kotlin (com.aishtech.poslite)
├── docs/        Foundation, rules, sprint evidence
├── scripts/     Local validation / smoke scripts
├── .github/     CI workflows
├── README.md
└── .gitignore
```

## Backend Setup

- Scaffolded with `composer create-project laravel/laravel backend` (Laravel 13.18.1).
- API routing enabled via `bootstrap/app.php` (`api: routes/api.php`).
- `GET /api/health` returns (no DB dependency):

  ```json
  { "status": "ok", "app": "Aish POS Lite API",
    "foundation": "POS_ANDROID_SAAS_FOUNDATION", "sprint": "Sprint 0" }
  ```

- `.env.example` targets PostgreSQL (`DB_CONNECTION=pgsql`, database `pos_app`).
- `tests/Feature/HealthTest.php` validates the endpoint using in-memory SQLite.
- Backend usage documented in [`backend/README.md`](../../backend/README.md).

## Android Setup

- Package `com.aishtech.poslite`, app name "Aish POS Lite".
- `minSdk = 26`, `targetSdk = 35`, `compileSdk = 35`, Kotlin, native XML/Views.
- Launchable placeholder `MainActivity`; no business features.
- Documented in [`android/README.md`](../../android/README.md).

## Scripts

- [`scripts/sprint0_smoke.sh`](../../scripts/sprint0_smoke.sh) — structure + hygiene
  checks, runnable without PHP/Gradle/Android SDK.

## CI

- [`.github/workflows/sprint0-ci.yml`](../../.github/workflows/sprint0-ci.yml) —
  three jobs: foundation/structure validation, backend composer + tests
  (PHP 8.3), Android structure validation. Intentionally light: no secrets, no
  Android SDK build.

## Validation Commands

```bash
# Foundation
grep -n "POS_ANDROID_SAAS_FOUNDATION" README.md docs/PROJECT_RULES.md
grep -n "Definition of Done MVP" docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md

# Smoke
bash scripts/sprint0_smoke.sh

# Backend
cd backend && composer validate --strict && php artisan test && cd ..

# Backend health (manual)
cd backend && php artisan serve --host=127.0.0.1 --port=8000 &
#   -> GET http://127.0.0.1:8000/api/health

# Android structure
grep -R "com.aishtech.poslite" android/app/build.gradle.kts
grep -E "minSdk|targetSdk" android/app/build.gradle.kts
```

## Validation Results

| Check                         | Result                                              |
| ----------------------------- | --------------------------------------------------- |
| Foundation grep (README/rules)| PASS                                                |
| Smoke script                  | PASS                                                |
| Backend `composer validate`   | PASS (`./composer.json is valid`)                   |
| Backend route `api/health`    | PASS (`GET|HEAD api/health`)                         |
| Backend tests                 | PASS (3 tests, 4 assertions)                         |
| Backend health via HTTP       | PASS (correct JSON payload returned)                |
| Android structure validation  | PASS                                                |
| Android build (`assembleDebug`)| SKIPPED — no Gradle/Android SDK in setup env        |
| Forbidden files check         | PASS (no `.env`/APK/AAB/vendor/node_modules tracked)|
| Working tree clean after commit| YES                                                |

### Tooling limitation (honest note)

The setup environment has PHP 8.5, Composer 2, JDK 25, and `gh`, but **no Gradle
wrapper and no Android SDK (`ANDROID_HOME` unset)**. The Android skeleton is
therefore validated structurally only; `./gradlew :app:assembleDebug` was **not**
run and is **not** claimed to pass. The Gradle wrapper is generated on first
Android Studio / local Gradle sync.

## GO Criteria

1. Foundation document remains source of truth. ✅
2. README and PROJECT_RULES reference the foundation. ✅
3. Backend skeleton present in `backend/`. ✅
4. Backend health endpoint present. ✅
5. Android skeleton present in `android/`. ✅
6. Android package name correct (`com.aishtech.poslite`). ✅
7. Android `minSdk = 26`. ✅
8. Android `targetSdk = 35`. ✅
9. Smoke script present and passing. ✅
10. No `.env`, APK, AAB, vendor, node_modules, or heavy build artifacts committed. ✅
11. Initial CI workflow present. ✅
12. Working tree clean after commit. ✅
13. Commit pushed to remote branch. ✅
14. GO tag created and pushed after validation. ✅

## No-Go Checks

None triggered. No-Go would apply if: foundation unreadable, README/rules stop
referencing the foundation, missing backend/android folders, missing health
endpoint, wrong package name, wrong SDK levels, failing smoke script, or any
`.env`/APK/AAB/vendor/node_modules committed. All clear.

## Follow-up for Sprint 1

- Sprint 1 — SaaS Tenant Foundation: introduce tenant model, tenant isolation,
  and Sanctum auth per foundation §8 and §12, with tenant-isolation tests (§8.4).
