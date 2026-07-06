# Sprint 3 — Android Cashier Foundation

## Objective

Build the real, lightweight Android cashier foundation: a native Kotlin app that
logs in against the Sprint 1 auth API, caches the Sprint 2 product/category
catalog locally with Room, searches it offline, and holds a cash-first local
cart — without implementing any sales submission, QRIS, printer, or inventory
runtime.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (canonical)
- `docs/PROJECT_RULES.md`
- `docs/sprints/sprint-0-project-setup.md`
- `docs/sprints/sprint-1-saas-tenant-foundation.md`
- `docs/sprints/sprint-2-product-foundation.md`

Foundation sections applied: 7 (Tech Stack), 8 (Multi-Tenant), 10 (Modul Android),
12 (API), 14 (Offline & Sync), 16 (Security), 17 (Performance), 18 (UI/UX),
21 (MVP Scope), 22 (Sprint Roadmap), 25 (No-Go Rules), 26 (Definition of Done).

## Previous Sprint Foundation Lock

Sprint 0 (monorepo structure), Sprint 1 (Sanctum auth + tenant context), and
Sprint 2 (tenant-isolated product catalog + `/sync/products` + `/sync/categories`)
remain the base. Their PROJECT_RULES runtime rules are unchanged; Sprint 3 adds a
new Android Cashier Foundation Runtime Rule on top and extends the Foundation Lock
Index to include this document.

## Scope

In scope: Android app structure, login screen + auth API consumption,
token/session storage (no password), Retrofit/OkHttp API client, Room local
catalog (products/categories/settings), incremental catalog sync manager, local
product search with LIMIT, cash-first in-memory cart, lightweight cashier UI,
unit tests, smoke script, CI, rules lock, evidence.

Out of scope (No-Go for Sprint 3): sales/transaction backend, cart submission,
cash payment finalization backend, QRIS, payment webhook, printer, inventory
movement runtime, subscription billing logic, advanced design system, dashboards.

## Graphify Summary

```
foundation rules ─┬─ Android lightweight (native Kotlin, Views/XML, minSdk 26/targetSdk 35)
                  ├─ backend payment only (no gateway keys/calls on device)
                  └─ offline-first catalog (Room cache, local search)

Sprint 1 auth API ──► AuthRepository ──► SessionManager ──► TokenStore (no password)
Sprint 2 sync API ──► CatalogSyncManager ──► Room (products/categories) ──► CatalogRepository (search LIMIT)
                                    │
Login screen ──► token ──► AuthInterceptor (Bearer) ──► PosApiService (Retrofit/OkHttp)
Cashier screen ──► search (Room) + CartRepository (in-memory, cash-first) + Sync button/status

validation gates ──► sprint3_smoke.sh + backend php artisan test + Android static validation
GO tag ──► depends on main containing Sprint 3 + all gates green
```

## Android Implementation

Package `com.aishtech.poslite`, `minSdk = 26`, `targetSdk = 35`, native Kotlin +
Android Views/XML + ViewBinding. Structure:

- `core/config/AppConfig.kt` — base URL + limits
- `core/util/ResultState.kt` — success/error/loading wrapper
- `core/network/` — `ApiClient`, `PosApiService`, `AuthInterceptor`
- `core/session/` — `TokenStore` (SharedPreferences), `SessionManager`
- `core/database/` — `PosDatabase` (Room), `Converters`
- `core/ServiceLocator.kt` — manual DI wiring (no heavy framework)
- `data/local/entity/` — `LocalProductEntity`, `LocalProductCategoryEntity`, `AppSettingEntity`
- `data/local/dao/` — `ProductDao`, `ProductCategoryDao`, `AppSettingDao`
- `data/local/CatalogMappers.kt` — pure DTO→entity mapping (unit-tested)
- `data/remote/dto/` — `AuthDtos`, `ProductSyncDtos`, `CommonDtos`
- `data/repository/` — `AuthRepository`, `CatalogRepository`, `CartRepository`
- `feature/auth/` — `LoginActivity`, `LoginViewModel`
- `feature/cashier/` — `CashierActivity`, `CashierViewModel`, `CartItem`, `ProductListAdapter`
- `feature/sync/CatalogSyncManager.kt`
- `MainActivity.kt` — launcher/router → login or cashier

Dependencies added: AndroidX activity/constraintlayout/recyclerview, Lifecycle
ViewModel+LiveData, Coroutines, Retrofit + Gson converter, OkHttp + logging
interceptor (debug-only, `Authorization` redacted), Room runtime/ktx + compiler
via KSP.

## Auth/Login Foundation

`LoginActivity` collects email/password, shows a loading indicator and error
text, and calls `AuthRepository.login` → `POST /api/v1/auth/login`. On success the
bearer token is stored and `CashierActivity` opens. The password is never stored.
If a token already exists, login is skipped and the cashier opens directly.

## API Client

`ApiClient` builds Retrofit (`AppConfig.DEFAULT_API_BASE_URL = http://10.0.2.2:8000/`,
emulator alias for host localhost) with a Gson converter and an OkHttp client
carrying `AuthInterceptor`. The logging interceptor is attached only in debug
builds and redacts the `Authorization` header. `PosApiService` exposes
`login`, `me`, `logout`, `syncProducts(updated_since, store_id)`,
`syncCategories(updated_since, store_id)`.

## Token/Session Storage

`TokenStore` (interface) + `SharedPrefsTokenStore` implement
`saveToken/getToken/clearToken/isLoggedIn`. `SessionManager` is the facade used by
repositories. Sprint 3 uses plain SharedPreferences as an acceptable fallback with
a `TODO(secure-storage)` to harden to EncryptedSharedPreferences/Keystore later.
No password is ever written to disk.

## Room Local Catalog

`PosDatabase` (version 1) holds `products`, `product_categories`, and
`app_settings`. Entities mirror the sync payload; prices are stored as `Double`
after defensive parsing of the backend decimal strings. `app_settings` stores the
incremental cursors `last_products_sync_at` / `last_categories_sync_at`.

## Product/Category Sync

`CatalogSyncManager.sync()` reads the stored `updated_since` cursors, calls
`/sync/categories` then `/sync/products`, upserts results via Room, and advances
both cursors only after both upserts succeed. A failed sync returns a
`ResultState.Error` and never clears the local cache. Sync is manual (the cashier
"Sync" button); WorkManager is intentionally not activated in Sprint 3, and no
offline sales sync is implemented.

## Local Product Search

`ProductDao.searchActiveProducts` matches `name`/`sku`/`barcode` with `LIKE`,
filters `isActive = 1`, and applies `LIMIT 50`. Empty query falls back to
`getActiveProducts(LIMIT 200)`. Search runs entirely on-device against Room — no
network round trip and no full-table load, keeping older devices responsive.

## Cart Cash-First Foundation

`CartRepository` is a framework-free, in-memory cash-first cart:
`addProduct` (increments existing line), `updateQuantity` (0 removes),
`removeProduct`, `clear`, `items`, `itemCount`, `subtotal`. `CartItem` carries
`productId/name/unitPrice/quantity` with a computed `lineTotal`. The cart is
local-only; nothing is submitted to the backend. The cashier screen shows a
disabled "Checkout (Sprint 4)" button as a placeholder.

## Lightweight Performance Rules

RecyclerView + DiffUtil for the product list; bounded search/list limits; no
product images; no heavy animations; sync failures surface as status text without
crashing; explicit empty state prompting the user to Sync.

## Application Rules Update

`docs/PROJECT_RULES.md`:
- Foundation Lock Index now includes `sprint-3-android-cashier-foundation.md`.
- Added "Sprint 3 Android Cashier Foundation Runtime Rule" (15 mandatory rules).
- Sprint 0/1/2 rules preserved unchanged.

`backend/config/pos_foundation.php`: added `sprint_3 => 'Android Cashier
Foundation'` and rules `android_cashier_foundation_required`,
`android_local_catalog_required` (metadata only — no backend behavior change).

## Testing Evidence

Pure-JVM unit tests under `android/app/src/test/java/com/aishtech/poslite/`:
- `CartRepositoryTest` — add/increment/update-qty/remove/clear/subtotal.
- `CatalogMappingTest` — DTO→entity mapping, `effective_selling_price` fallback,
  malformed-price → 0, inactive product preserved as inactive.

These are framework-free (no Android runtime), so they execute under Android
Gradle unit-test tooling. See the limitation note below on local execution.

## Backend Compatibility Evidence

No backend runtime behavior changed. Only `config/pos_foundation.php` metadata was
extended. The auth (`/api/v1/auth/login|me|logout`) and sync
(`/api/v1/sync/products|categories`) routes remain intact and their tests pass.

## Validation Commands

```bash
bash scripts/sprint3_smoke.sh
cd backend && composer validate --strict && php artisan route:list | \
  grep -E "api/v1/auth/login|api/v1/sync/products|api/v1/sync/categories" && php artisan test && cd ..
# Android static validation
grep -R "com.aishtech.poslite" android/app/build.gradle.kts
grep -R "minSdk = 26" android/app/build.gradle.kts
grep -R "targetSdk = 35" android/app/build.gradle.kts
```

## Validation Results

- Foundation/rules grep: PASS
- Sprint 3 smoke: PASS
- Backend `composer validate --strict`: PASS
- Backend route compatibility (auth/login, sync/products, sync/categories): PASS
- Backend `php artisan test`: PASS
- Android static validation (package/minSdk/targetSdk/sources/endpoints): PASS
- Android build (`assembleDebug`): SKIPPED — see limitation
- Android unit tests (`testDebugUnitTest`): SKIPPED — see limitation
- Forbidden files check: PASS
- Working tree clean after commit: YES

### Android build/test limitation (honest note)

The local environment has JDK 25 only, no `gradle` binary, and no committed
Gradle wrapper. Android Gradle Plugin 8.7.3 supports JDK 17–21, so the app cannot
be assembled or its unit tests executed locally in this environment. The Android
sources, Gradle config, and JVM-only tests are complete and committed; CI performs
static validation. Generating the Gradle wrapper and enabling
`assembleDebug`/`testDebugUnitTest` on an Android CI runner is a follow-up. No
build/test PASS is claimed where it was not actually run.

## GO Criteria

1. Foundation remains source of truth — met.
2. Sprint 0/1/2/3 rules present in `docs/PROJECT_RULES.md` — met.
3. Android package `com.aishtech.poslite` — met.
4. `minSdk = 26` — met.
5. `targetSdk = 35` — met.
6. `LoginActivity` present — met.
7. `CashierActivity` present — met.
8. Retrofit/OkHttp API client present — met.
9. TokenStore/SessionManager present, no password stored — met.
10. Backend auth endpoint consumable by Android layer — met.
11. Room database present — met.
12. Local product/category entities present — met.
13. Product/category DAOs present — met.
14. `CatalogSyncManager` present — met.
15. Sync products/categories consumed by Android layer — met.
16. `updated_since` incremental foundation present — met.
17. Local product search with LIMIT — met.
18. Cart add/update/remove/clear/subtotal — met.
19. No sales submit / QRIS / webhook / printer / inventory runtime — met.
20. Sprint 3 smoke passes — met.
21. Backend compatibility tests pass — met.
22. Android build/test pass if tooling available, else limitation recorded — recorded.
23. Forbidden files not committed — met.
24. PR/merge complete — met (see PR/tag notes at release).
25. GO tag exact-match to main HEAD — created at release.

## No-Go Checks

- Foundation/rules readable: OK
- Sprint 0/1/2 rules retained: OK
- Sprint 3 runtime rule present: OK
- Android package unchanged: OK
- minSdk 26 / targetSdk 35: OK
- No password stored on device: OK
- No payment gateway key on device: OK
- No direct payment gateway call: OK
- Room local catalog present: OK
- Product sync foundation present: OK
- Local search present: OK
- Cart foundation present: OK
- No hidden QRIS/payment/printer/sales backend: OK
- Backend auth/sync routes intact: OK
- Backend tests pass: OK
- Smoke passes: OK
- No forbidden files committed: OK
- Working tree clean: OK

## Follow-up for Sprint 4

- Sales backend integration: cart submission, cash payment finalization.
- Generate Gradle wrapper; enable `assembleDebug` + `testDebugUnitTest` in CI.
- Harden token storage to EncryptedSharedPreferences/Keystore.
- Optionally activate WorkManager for background catalog sync.
