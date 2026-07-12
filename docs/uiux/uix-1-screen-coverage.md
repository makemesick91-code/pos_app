# UIX-1 — Screen Coverage Matrix

Status vocabulary: **COVERED** (foundation implemented & applied to a real, existing surface) ·
**IMPLEMENTATION REQUIRED** (spec'd screen not yet built in the app — backlog that will consume the
locked foundation) · **NOT APPLICABLE** · **BLOCKED BY MISSING PRODUCT RULE / FRONTEND**.

No `UNKNOWN` remains. "COVERED" here means the design foundation (tokens, styles, canonical microcopy,
theme, product-rule guardrails) is implemented and, where a screen exists, applied to it. UIX-1 is a
**foundation lock**, not a from-scratch build of the ~30 spec'd screens.

## Android — existing screens (foundation applied)

| Handoff | Screen (repo) | Foundation | Loading | Empty | Error | Offline | Permission | Entitlement | Status |
|---|---|---|---|---|---|---|---|---|---|
| A1/A4 | `MainActivity` (entry gate) | ✓ theme/tokens | n/a | n/a | ✓ | n/a | ✓ gate order | ✓ backend | COVERED |
| A2/A3 | `LoginActivity` | ✓ tokens (error→destructive) | ✓ | n/a | ✓ | n/a | n/a | n/a | COVERED |
| B1/C1/C4/D1 | `CashierActivity`(+`item_product`) | ✓ tokens, tnum, low-stock→warning | ✓ | ✓ empty catalog | ✓ | ✓ offline copy | ✓ role | ✓ | COVERED |
| E1–E6 | `QrisPaymentActivity` | ✓ tokens, canonical status copy | ✓ | n/a | ✓ | ✓ blocked-offline | ✓ | ✓ | COVERED |
| F1/F2 | `ReceiptActivity` | ✓ tokens, offline header string | ✓ | n/a | ✓ | ✓ offline label | ✓ | ✓ | COVERED |
| J1/J2 | `ReportsActivity` | ✓ tokens, tnum | ✓ | ✓ | ✓ | n/a | ✓ owner | ✓ | COVERED |
| A5/A6/A7/K2/K3 | `SubscriptionStatusActivity` | ✓ tokens, block-screen copy | ✓ | n/a | ✓ retry | n/a | ✓ | ✓ backend decision | COVERED |

## Android — spec'd, not yet built (backlog consuming the foundation)

| Handoff | Module | Status |
|---|---|---|
| C2/C3 | POS search states | IMPLEMENTATION REQUIRED |
| C5 | Payment method chooser | IMPLEMENTATION REQUIRED |
| D2/D3 | Cash short / success dedicated screens | IMPLEMENTATION REQUIRED |
| F3 | Printer setup (Bluetooth discovery) | IMPLEMENTATION REQUIRED |
| G1/G2 | Sync queue / failure detail | IMPLEMENTATION REQUIRED |
| H1/H2 | Transaction history list/detail | IMPLEMENTATION REQUIRED |
| I1/I2/I3 | Products & stock management | IMPLEMENTATION REQUIRED |
| K1/K4 | Role-aware "More" menu / "Segera Hadir" | IMPLEMENTATION REQUIRED |
| Tablet T1–T4 | Two-pane POS, list-detail | IMPLEMENTATION REQUIRED |
| Void/refund/discount/split/customer/import/notifications | Future-state (no backend) | BLOCKED BY MISSING PRODUCT RULE — render as "SEGERA HADIR" (UIX-R015) |

## Web console

| Handoff | Surface | Status |
|---|---|---|
| — | Public website (home/packages/privacy/terms/thank-you) | COVERED (palette aligned to foundation) |
| W1 Overview / W2 Tenant / W3 Renewals / W4 Devices / W5 Onboard / W6 Audit | Platform admin console | BLOCKED BY MISSING FRONTEND — backend is **API-only** (Sprints 11–36); no web console UI exists to align. Tokens (`aish-tokens.css`) are ready for when that frontend is built. |

## Global states (L — all screens)

| State | Foundation support | Status |
|---|---|---|
| Skeleton / loading | theme + styles | COVERED (pattern) |
| Error + retry (3-part) | error pattern documented; `uix_sync_failed_detail` | COVERED (pattern) |
| Empty (relevant CTA) | pattern documented | COVERED (pattern) |
| Offline / stale timestamp | `uix_offline_banner`, canonical sync labels | COVERED |
| Destructive confirm | `uix_clear_cart_confirm` + pattern | COVERED |
| Duplicate-action guard | `uix_closing_done` (idempotent replay) | COVERED |
| Mandatory update | pattern documented | IMPLEMENTATION REQUIRED |

## Financial / QRIS state accuracy (UIX-R011)

Cash success/failure, QRIS pending/verifying/paid/expired/failed, settlement-pending — all mapped to
canonical `uix_qris_*` / `uix_sync_*` strings; **never** show success before backend confirms. Settlement is
kept distinct from payment status.
