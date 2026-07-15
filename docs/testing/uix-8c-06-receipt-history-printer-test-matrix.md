# UIX-8C-06 — Receipt / History / Printer Test Matrix

Automated coverage (pure-JVM JUnit4 + coroutines-test + arch core-testing) plus the
backend regression fence. Physical receipt/history/printer/large-font/TalkBack
validation is operator-performed and deferred to final code freeze (R209).

## Android unit tests

| Scenario | Rule | Test |
|----------|------|------|
| Local pending → OFFLINE_PENDING, never synced | R175/R176 | `ReceiptProjectorTest.localPending_projectsOfflinePending_neverSynced` |
| Local synced → SYNCED, invoice reference | R176 | `ReceiptProjectorTest.localSynced_projectsSynced` |
| Unknown status fails safe to pending | R147/R176 | `ReceiptProjectorTest.unknownStatus_failsSafeToOfflinePending_neverSynced` |
| Receipt binds clientReference/serverSaleId/localId | R172 | `ReceiptProjectorTest.localProjection_bindsIdentity` |
| Local money/items exact (whole rupiah) | R177/R179 | `ReceiptProjectorTest.localProjection_moneyAndItemsAreExact` |
| Server decimal strings → exact Long (no 100× bug) | R177/R179 | `ReceiptProjectorTest.serverReceipt_parsesDecimalStringsToExactWholeRupiah` |
| Online = ONLINE_SUCCESS; synced flag = SYNCED | R174/R176 | `ReceiptProjectorTest.serverReceipt_onlineIsSuccessState_syncedFlagMapsToSynced` |
| Server projection binds ids + context | R172/R180 | `ReceiptProjectorTest.serverReceipt_bindsServerIdAndCarriesClientReference` |
| Server sale ready online + printable | R174 | `ReceiptViewModelTest.loadServerSale_ready_online_and_printable` |
| Local pending ready offline + not printable | R175/R191 | `ReceiptViewModelTest.loadLocalPending_ready_offline_and_notPrintable` |
| Synced restored from Room, reprint enabled | R187/R189 | `ReceiptViewModelTest.loadLocalSynced_restoresFromRoom_andEnablesReprint` |
| Stale/identity-mismatch not published | R173/R190 | `ReceiptViewModelTest.staleData_identityMismatch_isNotPublished` |
| Print event fires exactly once | R190 | `ReceiptViewModelTest.print_firesEventExactlyOnce` |
| Pending receipt does not print | R191 | `ReceiptViewModelTest.print_onPendingReceipt_doesNotPrint` |
| Local pending → one PENDING row | R181/R184 | `TransactionHistoryReconcilerTest.localPendingOnly_isOnePendingRow` |
| Local+server same ref → one SYNCED row | R181/R182 | `...localAndServer_sameReference_mergeToOneSyncedRow` |
| Server only → one SYNCED row | R181 | `...serverOnly_isOneSyncedRow` |
| Different refs do not merge | R182/R183 | `...differentReferences_doNotMerge` |
| Total mismatch → CONFLICT (no silent merge) | R160/R184 | `...sameReference_mismatchedTotal_isConflict_notSilentMerge` |
| Local CONFLICT status → conflict row | R184 | `...localConflictStatus_isConflictRow` |
| FAILED under cap → RETRY_SCHEDULED; at cap → FAILED | R184 | `...failedUnderCap_isRetryScheduled_atCapIsFailed` |
| Newest-first, stable order | R186 | `...rows_areOrderedNewestFirst_andStable` |
| Repeated reconcile idempotent | R186 | `...repeatedReconcile_isIdempotent_noDuplicates` |
| Coordinator delegates | R191 | `PrinterCoordinatorTest.print_delegatesToPrinter` |
| Typed failure passes through | R197 | `...typedFailure_passesThroughUnchanged` |
| Second concurrent print rejected | R198 | `...secondConcurrentPrint_isRejectedAsAlreadyPrinting` |
| Reprint reuses same receipt, no second path | R193 | `...reprint_reusesSameReceipt_andCreatesNoSecondLogicalPrintPath` |
| Permission required/denied/unsupported/disabled/invalid typed | R197 | `BluetoothPrinterConnectionTest` (6 typed assertions) |
| Receipt/history state distinct labels, not colour-only | R205 | `ReceiptHistoryStateDisplayTest` |
| Receipt/history structural font-scale + touch target | R202/R206 | `ReceiptHistoryLayoutTest` |

## Backend regression fence

| Scenario | Rule | Test |
|----------|------|------|
| Receipt mirrors canonical sale totals/items | R177/R178 | `Uix8c06ReceiptHistoryParityTest.test_receipt_mirrors_canonical_sale_totals_and_items` |
| Replayed reference → one sale/receipt (no dup history) | R181 | `...test_replayed_reference_is_one_sale_one_receipt_no_duplicate_history` |
| Receipt tenant-scoped (foreign tenant 404) | R183 | `...test_receipt_is_tenant_scoped_foreign_tenant_cannot_read` |

## Deferred to physical (post code freeze)

Visual receipt/history rendering, on-device large-font (100/115/130%) observation,
TalkBack focus order + spoken labels, real Bluetooth printer connect/print/timeout/
write-failure, and reprint on hardware. Emulator/fake-adapter evidence is never
labelled physical.
