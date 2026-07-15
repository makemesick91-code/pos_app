# UIX-8C-07 — Preflight Baseline Evidence

Sprint: **UIX-8C-07 — Premium Authentication, Device Activation, Settings & Session Recovery**
Captured: 2026-07-15 (session start)

## Git baseline
| Ref | SHA |
|-----|-----|
| local `main` (pre-branch HEAD) | `1e37a936a4d1feaa902b9af0860f1033e3f5986b` |
| `origin/main` | `1e37a936a4d1feaa902b9af0860f1033e3f5986b` |
| `uix-8c-06-…-go` tag (peeled) | `1e37a936a4d1feaa902b9af0860f1033e3f5986b` |
| Expected UIX-8C-06 evidence anchor | `1e37a93` ✅ match |
| Working tree | clean |
| Implementation branch | `feature/uix-8c-07-premium-auth-device-settings-session-recovery` |

Baseline is NOT ahead of `1e37a93` — no legitimate merges landed after UIX-8C-06; using `1e37a93` directly.

## VPS baseline (shared VPS, read-only capture)
| Item | Value |
|------|-------|
| Aish repo HEAD `/var/www/aish-pos` | `1e37a936a4d1feaa902b9af0860f1033e3f5986b` ✅ exact-match with origin |
| Aish worktree | clean |
| Service `aish-pos-queue-worker` | active |
| Service `php8.5-fpm` | active |
| Service `nginx` | active |

## DaengtisiaMS (co-tenant) — MUST remain unchanged & healthy
| Item | Value |
|------|-------|
| DMS `/var/www/asia-dental-lab-v2` HEAD (before) | `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4` ✅ matches expected `8b0bb6a` |
| (other) `/var/www/asia-dental-lab` HEAD | `1e55f850b15da20502f833be48f8aacf108eea9e` (legacy, out of scope) |
| Service `php8.3-fpm` | active |

DMS non-regression target for the after-check: `asia-dental-lab-v2` must stay at `8b0bb6a`, php8.3-fpm active.

## Tooling feasibility (probed)
- Android: `android/gradlew`, JDK 21, full SDK (`build-tools`, `emulator`, `system-images`, `platform-tools`), `DISPLAY=:0.0` (emulator boot feasible).
- Graphify: present (`~/.local/bin/graphify`); refreshed android graph → **2069 nodes, 3413 edges, 132 communities**.
- VPS SSH: `daengtisiams-vps` reachable.
- GitHub: `gh` authed as `makemesick91-code` (org `makemesick91-code/pos_app`).

## Execution boundary (user-authorized)
Full autonomous path: implement → tests/gates → PR → exact-SHA authoritative CI → merge → VPS deploy → emulator runtime evidence (labelled emulator) → evidence PR → local/origin/VPS exact-match → annotated sprint GO tag `uix-8c-07-premium-authentication-device-activation-settings-session-recovery-go`.

Honesty constraints carried forward: emulator evidence stays labelled emulator (UIX7-R071..R080); operator-observed accessibility/font-130% human PASS cannot be fabricated (rule 59); absence of proof = NO-GO; UIX-7 stays `NO-GO — GO DEFERRED`, UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`; sprint tag never asserts UIX-7/UIX-8 runtime closure.
