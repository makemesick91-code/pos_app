# Sprint 6 — Printer & Receipt Foundation

## Objective

Establish a correct, POS-grade receipt and printer foundation:

`Paid Sale → Receipt Preview → ESC/POS Format → Android Receipt Screen → Bluetooth Printer Foundation`

Receipts are backend-authoritative and tenant-isolated; Android only formats and
prints a backend-approved payload. A final printable receipt is produced only for
a PAID sale (CASH or QRIS); pending/unpaid/cancelled/expired/failed sales are not.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (canonical)
- `docs/PROJECT_RULES.md`
- Sprint 0–5 evidence in `docs/sprints/`

Foundation sections applied: 10 (Android modules), 12 (API), 13 (QRIS), 15
(Receipt Printer), 16 (Security), 17 (Performance), 18 (UI/UX), 21 (MVP Scope),
22 (Sprint Roadmap), 25 (No-Go Rules), 26 (Definition of Done).

## Previous Sprint Foundation Lock

Sprint 6 preserves and does not contradict Sprint 0–5. Cash checkout (Sprint 4)
and backend-driven QRIS (Sprint 5) behavior remain intact; all 122 pre-existing
+ new backend tests pass. PROJECT_RULES retains every prior runtime rule and adds
the Sprint 6 rule; the Foundation Lock Index now includes the Sprint 6 doc.

## Scope

In scope:

- Backend receipt preview API, service, resource, eligibility rules, tenant isolation, tests.
- Android receipt DTO/API/repository/screen, ESC/POS formatter, Bluetooth printer foundation, printer settings.
- Android Gradle wrapper committed; Android build CI running assembleDebug + testDebugUnitTest.
- Sprint 6 smoke script, CI workflow, rules lock, evidence.

Out of scope (deferred): real printer auto-discovery, vendor SDKs, cloud print,
live QRIS activation, payout/settlement/refund, offline sales sync, inventory
movement runtime, advanced reports, owner dashboard.

## Graphify Summary

- Sale header carries authoritative totals + payment_status (PAID/UNPAID/PENDING/CANCELLED/EXPIRED/FAILED).
- SaleItem carries immutable snapshots (product_name, product_sku, unit, qty, unit_price, discount, subtotal).
- Payment carries method (CASH/QRIS), provider, status, amount, paid_at; `raw_response` is hidden.
- `ReceiptService` loads sale + items + payments + store + cashier, decides eligibility from `sale.payment_status`, and builds the payload from snapshots only.
- `ReceiptController` enforces tenant ownership with a 404 (same shape as the sales API).
- Android `ReceiptRepository` → `ReceiptViewModel`/`ReceiptActivity` render the preview; `EscPosReceiptFormatter` → `PrinterRepository` → `BluetoothPrinterConnection` deliver the print; `PrinterSettingsStore` holds local printer config only.

## Backend Implementation

- `backend/app/Services/ReceiptService.php` — eligibility + snapshot-only payload builder.
- `backend/app/Http/Controllers/Api/V1/ReceiptController.php` — tenant-isolated show (404 on cross-tenant).
- `backend/app/Http/Resources/Api/V1/ReceiptResource.php` — `{ data, meta }` envelope.
- `backend/routes/api.php` — `GET /api/v1/sales/{sale}/receipt` under `auth:sanctum`, `tenant.active`, `tenant.context`.
- `backend/config/pos_foundation.php` — adds `sprint_6` + receipt/ESC-POS/wrapper/CI rule flags.

## Receipt API

`GET /api/v1/sales/{sale}/receipt` → `{ data: { sale_id, invoice_number,
receipt_status, printable, print_block_reason, store, cashier, sale_date,
payment_status, items[], payments[], totals{}, footer }, meta }`.

Not-printable sales return HTTP 200 with `printable=false` and a reason (never 500).

## Receipt Eligibility Rules

| Sale payment_status | receipt_status | printable |
| ------------------- | -------------- | --------- |
| PAID (CASH or QRIS) | FINAL          | true      |
| PENDING (QRIS)      | DRAFT          | false     |
| UNPAID              | NOT_PRINTABLE  | false     |
| CANCELLED           | NOT_PRINTABLE  | false     |
| EXPIRED             | NOT_PRINTABLE  | false     |
| FAILED              | NOT_PRINTABLE  | false     |

## Tenant Isolation Rules

Cross-tenant receipt access 404s (identical to a non-existent sale), so tenant A
cannot preview, print, or infer tenant B's receipt. Proven in
`ReceiptTenantIsolationTest`.

## Android Implementation

- `data/remote/dto/ReceiptDtos.kt` — receipt DTOs.
- `core/network/PosApiService.kt` — `GET api/v1/sales/{id}/receipt`.
- `data/repository/ReceiptRepository.kt` — relays backend receipt (no local totals/eligibility).
- `feature/receipt/ReceiptActivity.kt` + `ReceiptViewModel.kt` + `res/layout/activity_receipt.xml`.
- `feature/printer/EscPosReceiptFormatter.kt`, `PrinterConnection.kt`, `BluetoothPrinterConnection.kt`, `PrinterRepository.kt`, `PrinterSettingsStore.kt`.
- `AndroidManifest.xml` — Bluetooth permissions + `ReceiptActivity`.
- Navigation: cashier cash-success and QRIS PAID both open `ReceiptActivity` with the sale id.

## Receipt Screen

Shows invoice, receipt status, and a monospace text preview of the ESC/POS body.
The print button is disabled whenever `printable=false`, with the backend block
reason displayed. If no printer is configured, printing returns "Printer belum
dikonfigurasi" without crashing.

## ESC/POS Formatter

`EscPosReceiptFormatter` is pure Kotlin: `buildReceiptText` produces the 58mm
(32-col) / 80mm (48-col) body; `format` wraps it with `ESC @` init, line feeds,
and an optional `GS V 66 0` partial cut. No printer SDK. Unit-tested on the JVM.

## Bluetooth Printer Foundation

`BluetoothPrinterConnection` connects to a paired printer by MAC over the SPP
UUID and streams the ESC/POS bytes off the main thread. No discovery (hence no
location permission). Every failure surfaces as `PrintResult.Failure`, never a
crash. `PrinterRepository` refuses to print any receipt the backend did not mark
printable.

## Printer Settings

`PrinterSettingsStore` (SharedPreferences `aish_pos_printer`) holds only
`printer_name`, `printer_mac_address`, `paper_width_mm`, `auto_cut_enabled`. It
never stores payment credentials, passwords, or tokens.

## Gradle Wrapper Evidence

Committed official wrapper: `android/gradlew`, `android/gradlew.bat`,
`android/gradle/wrapper/gradle-wrapper.jar` (43,583 bytes), and
`gradle-wrapper.properties` pinned to Gradle 8.11 — compatible with AGP 8.7.3 and
JDK 17–21. Files were fetched from the official `gradle/gradle` v8.11.1 tag (not
fabricated). The local dev box runs JDK 25 with no Gradle binary, so the build
itself runs in CI on JDK 21.

## Android Build CI Evidence

`.github/workflows/sprint6-ci.yml` job `android-build-test`: JDK 21 + Android SDK,
`chmod +x android/gradlew`, `./gradlew :app:assembleDebug`, then
`./gradlew :app:testDebugUnitTest`. Not optional, no `continue-on-error`.

## Application Rules Update

`docs/PROJECT_RULES.md` adds the **Sprint 6 Printer & Receipt Foundation Runtime
Rule** (17 clauses) and extends the Foundation Lock Index to include this doc. All
prior sprint rules are retained.

## Testing Evidence

Backend (`php artisan test`): 122 passed / 406 assertions.

- `ReceiptApiTest` — CASH/QRIS paid → FINAL printable; pending → DRAFT not printable; unpaid/cancelled/expired/failed → NOT_PRINTABLE; snapshot (not live product); totals match; no `raw_response`/secret leak.
- `ReceiptTenantIsolationTest` — own receipt readable; tenant B receipt 404s; no cross-tenant inference.

Android unit tests (run in CI, `:app:testDebugUnitTest`): existing
Cart/Catalog/Sales/Qris tests plus new `EscPosReceiptFormatterTest` and
`ReceiptRepositoryTest`.

## Backend Compatibility Evidence

`GET /api/health`, auth, tenant-context, product/category sync, sales, cash
payment, QRIS create/status, and webhook routes are all intact (route
compatibility asserted in CI). No prior API changed shape.

## Validation Commands

```bash
bash scripts/sprint6_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- Foundation/rules grep: pass
- Sprint 6 smoke: pass
- Backend `composer validate --strict`: pass
- Backend route compatibility (incl. `sales/{sale}/receipt`): pass
- Backend tests: pass (122)
- Receipt eligibility tests: pass
- Receipt tenant isolation tests: pass
- Android static validation: pass
- Android `assembleDebug` / `testDebugUnitTest`: local skipped (JDK 25 / no Gradle binary); executed in CI (JDK 21)
- Android secret scan: pass (no gateway keys in source)
- Forbidden files check: pass

## GO Criteria

1. Foundation remains source of truth.
2. Sprint 0–6 rules present in `docs/PROJECT_RULES.md`.
3. Receipt backend API + service/resource available.
4. CASH paid → FINAL printable; QRIS paid → FINAL printable.
5. Pending/unpaid/failed/expired/cancelled → not final printable.
6. Receipt uses sale item snapshots.
7. Tenant A cannot access tenant B receipt.
8. Android receipt DTO/API/repository/screen present.
9. ESC/POS formatter + Bluetooth printer foundation + printer settings present.
10. Print button blocked when `printable=false`; Android stores no gateway credentials.
11. Cash/QRIS behavior intact.
12. Gradle wrapper committed; Android CI runs assembleDebug + testDebugUnitTest and is green on the tagged commit.
13. Smoke + backend tests pass; forbidden files absent; working tree clean.

## No-Go Checks

Final receipt for unpaid/pending/cancelled/expired/failed; receipt using live
product instead of snapshot; cross-tenant receipt access; missing receipt route/
service/controller; missing Android receipt screen/formatter/printer foundation;
print button active while `printable=false`; gateway key in Android source; missing
Gradle wrapper; Android CI not running assembleDebug/testDebugUnitTest or not
green; failing backend tests/smoke; changed package/minSdk/targetSdk; forbidden
files committed; dirty working tree.

## Follow-up for Sprint 7

Sprint 7 — Offline Cash & Sync Foundation: local offline cash capture with a
sync queue, conflict handling, and reconciliation with the backend sales API.
