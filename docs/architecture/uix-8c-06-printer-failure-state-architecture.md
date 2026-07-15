# UIX-8C-06 — Printer Failure-State Architecture

## Goal

Make printer reliability truthful and typed, and make the printer's
**non-financial authority** a type-level, gate-enforced invariant rather than a
convention.

## Components

```
ReceiptViewModel ──► PrinterCoordinator ──► ReceiptPrinter (PrinterRepository)
                          │                        │
                    AtomicBoolean guard      backend `printable` gate
                    (≤1 active job)          + EscPosReceiptFormatter
                                                    │
                                             PrinterConnection (BluetoothPrinterConnection)
                                                    │
                                             typed PrintResult
```

- `PrinterCoordinator` — the single print/reprint entry point. An `AtomicBoolean`
  admits at most one active job (a rapid double-tap or reprint-while-printing
  returns `AlreadyPrinting`, R198). It holds **no** reference to any sale, payment,
  offline, sync, or inventory type — non-financial by construction (R191/R192).
  Reprint calls the same method with the same immutable receipt: no new transaction
  (R193).
- `ReceiptPrinter` — narrow print seam so the coordinator/ViewModel are JVM-testable.
- `PrinterRepository` — backend-`printable` gate + ESC/POS format + typed outcome
  mapping (adds `NOT_PRINTABLE`, `DEVICE_NOT_CONFIGURED`).
- `BluetoothPrinterConnection` — paired BT Classic SPP; typed failures, bounded
  timeout, connect-vs-write distinction, catch-all.

## Typed failure states (`PrinterFailure`)

`PERMISSION_REQUIRED`, `PERMISSION_DENIED`, `UNSUPPORTED`, `ADAPTER_DISABLED`,
`DEVICE_NOT_CONFIGURED`, `DEVICE_UNAVAILABLE`, `CONNECTION_FAILED`, `TIMEOUT`,
`WRITE_FAILED`, `INTERRUPTED`, `NOT_PRINTABLE`, `UNKNOWN_SAFE_FAILURE`.

Each maps to an actionable, secret-free message and a `retryable` hint (a
config/eligibility problem is not retryable; transient failures are). No raw
exception payload reaches the user (R161/R199).

## Transport hardening

- Deny-by-default permission gate before any adapter access (unchanged from
  FIX-BT-SCAN).
- `withTimeoutOrNull(8s)` around connect+write so a hung printer cannot block the
  IO coroutine (R200); expiry → `TIMEOUT`.
- A `connected` flag distinguishes `CONNECTION_FAILED` from `WRITE_FAILED`.
- A catch-all `Exception` branch → `UNKNOWN_SAFE_FAILURE` (never a crash).
- No retry loop anywhere; the coordinator is the only "retry", and it is bounded to
  one active job.

## Permissions (least privilege)

`BLUETOOTH_CONNECT` (runtime, API 31+) + legacy `BLUETOOTH`/`BLUETOOTH_ADMIN`
(`maxSdkVersion=30`). **No `BLUETOOTH_SCAN`** and **no location permission** — the
transport connects to an already-paired device and never performs discovery
(R194/R195/R196; consistent with rule 58 / FIX-BT-SCAN).

## Financial isolation (enforced)

The sprint gate greps the `feature/printer` package for any reference to a
sale/payment/offline/sync/inventory repository and fails closed if found. A
print/reprint outcome never mutates transaction authority (R191/R192/R193).
