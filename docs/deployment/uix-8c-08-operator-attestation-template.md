# UIX-8C-08 — Physical Accessibility Operator Attestation

> **STATUS: NOT SIGNED / PENDING.** Claude/tooling must NOT mark any human-observed check as
> PASS. This form is completed by a **human operator** who personally observed the scenarios
> on the stated physical device using the stated APK. Without a genuine signed attestation:
> `PHYSICAL ACCESSIBILITY = NOT CLOSED` and `FINAL GO = PROHIBITED`.

---

## Run binding (filled at capture time)

| Field | Value |
|---|---|
| Physical run ID | `run-uix8c08-<UTC>-<candidate_short_sha>` _(PENDING)_ |
| Candidate SHA | _(PENDING)_ |
| APK SHA-256 | _(PENDING)_ |
| Device alias (sha256 of serial) | _(PENDING)_ |
| Manufacturer / Model | _(PENDING)_ |
| Android version / SDK | _(PENDING)_ |
| Font scale under test | 1.30 |
| TalkBack version / status | _(PENDING)_ |
| Operator name or approved alias | _(PENDING)_ |
| Observation start (UTC) | _(PENDING)_ |
| Observation end (UTC) | _(PENDING)_ |

---

## A. Font scale 130% (P23 / R18) — eyes-on

Operator observes on the physical device at font_scale 1.30, portrait, keyboard up where
applicable. Mark each; any ❌ = font row FAIL.

- [ ] Splash — no clipping
- [ ] Activation — no clipping, CTA visible & reachable
- [ ] Cashier login — no clipping, CTA reachable
- [ ] Dashboard — tenant / outlet / cashier context readable
- [ ] Cart — quantities, totals readable
- [ ] Payment / CASH confirm — amount due, tender, change readable; **confirm CTA reachable**
- [ ] Payment result — readable
- [ ] Receipt — readable, whole-Rupiah unambiguous
- [ ] Transaction history — readable
- [ ] Settings — readable
- [ ] Session-expired — CTA visible
- [ ] Revoked-device — CTA visible
- [ ] Logout-blocked guard — count/reason readable
- [ ] Keyboard/IME does not cover any CTA
- [ ] Dialogs scrollable, follow window insets
- [ ] Narrow portrait layout usable, scroll available, no critical clip

**Font 130% result:** ▢ PASS ▢ FAIL

## B. TalkBack (P24 / R18) — operator-heard

- [ ] Every field has a meaningful label
- [ ] Every button announced as actionable (no bare "button")
- [ ] Focus order follows the visible cashier workflow
- [ ] No focus loop / no duplicate meaningless focus
- [ ] Errors are announced
- [ ] Loading / status changes are understandable
- [ ] Disabled actions are understandable
- [ ] Tenant / outlet / cashier context readable via TalkBack
- [ ] Status not conveyed by colour alone (text label always present)
- [ ] Receipt / history navigation usable

**TalkBack result:** ▢ PASS ▢ FAIL

## C. Keyboard / IME (P25)

**Result:** ▢ PASS ▢ FAIL

## D. Portrait / narrow layout (P26)

**Result:** ▢ PASS ▢ FAIL

---

## Observed defects

`None` / _(list)_

## Operator statement

> I personally observed the scenarios above on the stated physical device using the stated
> APK and confirm that this report reflects the actual result. I have not relied on emulator
> output for any physical check.

| | |
|---|---|
| Operator signature / approval | _(PENDING)_ |
| Timestamp (UTC) | _(PENDING)_ |

---

**If any of A–D is FAIL or unsigned → UIX-8C-08 FINAL GO = `NO_GO`.**
