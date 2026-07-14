# UIX-8C — Screen / State / Accessibility Matrix

Sprint: **UIX-8C-01**. Rule set: `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
(UIX8C-R001..R030). Every screen must define **loading, empty, error, offline,
success** (UIX8C-R006) and satisfy the accessibility gates (UIX8C-R019..R022).
This matrix is authored in UIX-8C-01 as the acceptance contract for UIX-8C-02..09;
it does not assert runtime PASS (that is captured only on genuine evidence,
UIX8C-R030).

## State legend

- **loading** — work in flight; cart never erased (UIX8C-R014).
- **empty** — no data; explains next action.
- **error** — actionable recovery; "Tidak tersedia", never fabricated zero.
- **offline** — cached/queued; transport-failure only (UIX8C-R012/R013).
- **success** — canonical confirmation (server ack or durable save).

## Accessibility gates (per screen, UIX8C-R019..R022)

- **A1 TalkBack** — meaningful spoken labels for every control.
- **A2 Focus order** — follows operational flow.
- **A3 Semantic labels** — icon-only controls have accessible names.
- **A4 Touch target** — >= 48dp.
- **A5 Font 130%** — primary workflow operable, no collapse (R18 target).
- **A6 Not colour-alone** — status carries a text label.
- **A7 Long names** — tenant/outlet/product names never break layout (UIX8C-R023).

## Matrix

| Group | Screen / state | loading | empty | error | offline | success | A1 | A2 | A3 | A4 | A5 | A6 | A7 | Owner |
| --- | --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | --- |
| Auth | Splash | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Auth | Activation | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Auth | Login | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Auth | Expired session | - | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Auth | Activation failure | - | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Auth | Device unavailable | - | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Auth | Logout / account switch | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-03 |
| Cashier | Home | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Context header (R01) | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Products | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Search | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Categories | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Empty catalog | - | Y | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | No-match | - | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Unavailable / error | - | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cashier | Cached / offline catalog | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-04 |
| Cart | Cart | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-05 |
| Cart | Empty cart | - | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-05 |
| Payment | Cash payment sheet | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Quick tender | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Manual tender | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Insufficient cash | - | - | Y | - | - | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Submitting | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Online success | - | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Offline queued (R11) | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Payment | Canonical server rejection | - | - | Y | - | - | Y | Y | Y | Y | Y | Y | Y | 8C-06 |
| Sync | Pending | Y | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Syncing | Y | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Synced | - | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Retrying | Y | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Failed | - | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Conflict | - | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Reconnect | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Sync | Orphan-SYNCING recovery | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-07 |
| Receipt | Current receipt | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| Receipt | Offline receipt | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| Receipt | Synced receipt | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| History | Transaction history | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| History | Empty history | - | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| History | Pending history | Y | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| History | Failed history | - | - | Y | Y | - | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| History | Transaction detail | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-08 |
| Settings | Cashier identity | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |
| Settings | Tenant / outlet | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |
| Settings | Device status | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |
| Settings | App version | - | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |
| Settings | Network / sync status | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |
| Settings | Printer status | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |
| Settings | Logout | Y | - | Y | - | Y | Y | Y | Y | Y | Y | Y | Y | 8C-09 |

`-` = state not applicable to that screen. `Y` = required and owned by the named
UIX-8C sub-sprint. Accessibility columns A1–A7 are release gates for every
screen (UIX8C-R020/R021/R022); a `Y` is a target, converted to PASS only on
genuine operator/emulator evidence per the active runtime-evidence governance
(rule 55 UIX7-R062, R071..R080). R18 (font 130% / A5) and R01 (identity /
context header) stay open until their owner sprint plus physical confirmation.
